<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\MigrateGuestRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Jobs\SendOtpJob;
use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Handles registration, login, logout, and JIT guest migration.
 * SRS FR-1.3, FR-1.4, FR-1.5, FR-1.6, NFR-SEC-2.
 */
class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    /**
     * Full user registration.
     * SRS FR-1.3: account stays UNVERIFIED until both OTP and email are verified.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            return User::create([
                'full_name' => $request->input('full_name'),
                'phone'     => $request->input('phone'),
                'email'     => $request->input('email'),
                'password'  => $request->input('password'), // hashed via cast
                'type'      => 'buyer',
                'status'    => 'active',
            ]);
        });

        // Dispatch phone OTP for verification
        $otp = $this->otpService->generate($user->phone, 'verify', $request->ip());
        SendOtpJob::dispatch($user->phone, $otp->code);

        // Dispatch email verification
        $user->notify(new EmailVerificationNotification());

        return response()->json([
            'message' => 'Registration successful. Please verify your phone via OTP and your email via the link sent.',
            'user'    => new UserResource($user),
        ], 201);
    }

    /**
     * Login with email or phone + password.
     * SRS NFR-SEC-2: 5 failed attempts → 15-minute lockout.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $login = $request->input('login');

        // Determine if login is email or phone
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = User::where($field, $login)->first();

        if (! $user) {
            return response()->json([
                'error'   => 'INVALID_CREDENTIALS',
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // Check if account is locked
        if ($user->locked_until && Carbon::now()->isBefore($user->locked_until)) {
            $unlockAt = Carbon::parse($user->locked_until)->toIso8601String();

            return response()->json([
                'error'      => 'ACCOUNT_LOCKED',
                'message'    => 'Account is temporarily locked due to too many failed attempts.',
                'unlock_at'  => $unlockAt,
            ], 423);
        }

        if ($user->isSuspended()) {
            return response()->json([
                'error'   => 'ACCOUNT_SUSPENDED',
                'message' => 'Your account has been suspended.',
            ], 403);
        }

        if (! Hash::check($request->input('password'), $user->password)) {
            // Increment failed attempts
            $attempts = $user->login_attempt_count + 1;
            $updates  = ['login_attempt_count' => $attempts];

            if ($attempts >= 5) {
                $updates['locked_until']        = Carbon::now()->addMinutes(15);
                $updates['login_attempt_count'] = 0; // reset for next cycle
            }

            $user->update($updates);

            return response()->json([
                'error'            => 'INVALID_CREDENTIALS',
                'message'          => 'Invalid credentials.',
                'attempts_remaining' => max(0, 5 - $attempts),
            ], 401);
        }

        // Successful login — reset counters
        $user->update([
            'login_attempt_count' => 0,
            'locked_until'        => null,
        ]);

        // Token TTL: 30-day sliding (Buyer/Delala), 8-hour hard (Admin)
        $ttl = match (true) {
            $user->isAdmin() => Carbon::now()->addHours(8),
            default          => Carbon::now()->addDays(30),
        };

        $token = $user->createToken('auth-token', ['role:'.$user->type], $ttl);

        return response()->json([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $ttl->toIso8601String(),
            'user'       => new UserResource($user),
        ]);
    }

    /**
     * Logout — revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Get current authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => new UserResource($request->user())]);
    }

    /**
     * JIT Guest → Registered Buyer migration.
     * SRS FR-1.4: updates existing user in-place. No FK changes.
     * SRS FR-1.5: 409 if email already taken by another user.
     */
    public function migrateGuest(MigrateGuestRequest $request): JsonResponse
    {
        $guest = $request->user();
        $email = $request->input('email');

        // FR-1.5: Check email collision
        $existing = User::where('email', $email)
            ->where('id', '!=', $guest->id)
            ->first();

        if ($existing) {
            return response()->json([
                'error'   => 'EMAIL_ALREADY_REGISTERED',
                'message' => 'This email address is already associated with another account.',
            ], 409);
        }

        DB::transaction(function () use ($guest, $request, $email) {
            $guest->update([
                'email'    => $email,
                'password' => $request->input('password'),
                'type'     => 'buyer',
            ]);
        });

        // Revoke old guest tokens
        $guest->tokens()->delete();

        // Dispatch email verification
        $guest->notify(new EmailVerificationNotification());

        // Issue new 30-day buyer token
        $ttl   = Carbon::now()->addDays(30);
        $token = $guest->createToken('auth-token', ['role:buyer'], $ttl);

        return response()->json([
            'message'    => 'Account upgraded successfully. Please verify your email.',
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $ttl->toIso8601String(),
            'user'       => new UserResource($guest->fresh()),
        ]);
    }
}
