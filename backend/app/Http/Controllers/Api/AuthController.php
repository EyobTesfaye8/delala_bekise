<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Notifications\VerifyEmailMagicLink;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /**
     * Register a new buyer account directly.
     * Starts as UNVERIFIED until SMS OTP and Email Magic Link are completed.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^(\+251|0)[97]\d{8}$/', 'unique:users,phone'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'full_name' => $request->full_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'type' => 'buyer',
            'status' => 'unverified',
        ]);

        // Send OTP to phone
        $this->authService->generateOtp($user->phone);

        // Send Magic Link to Email
        $user->notify(new VerifyEmailMagicLink());

        return response()->json([
            'message' => 'Registration successful. Please verify your phone and email.',
            'user' => $user,
        ], 201);
    }

    /**
     * Request an OTP for authentication.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^(\+251|0)[97]\d{8}$/'],
        ]);

        $this->authService->generateOtp($request->phone);

        return response()->json([
            'message' => 'OTP sent successfully',
        ]);
    }

    /**
     * Verify OTP and login/register as Guest or authenticates existing user.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        if (! $this->authService->validateOtp($request->phone, $request->code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided OTP is invalid or expired.'],
            ]);
        }

        $user = $this->authService->authenticateGuest($request->phone);

        // Revoke any old tokens if we only want one active device, or just create a new one.
        // For guests, we create an ephemeral token valid for 24 hours.
        $expiresAt = $user->isGuest() ? now()->addHours(24) : null;

        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'message' => 'Authentication successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Migrate a Guest to a Registered Buyer.
     */
    public function migrateGuest(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isGuest()) {
            return response()->json(['message' => 'User is already registered.'], 400);
        }

        $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'type' => 'buyer',
        ]);

        return response()->json([
            'message' => 'Account upgraded successfully',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Handle Email Magic Link Verification.
     */
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid or expired verification link.'], 403);
        }

        if (! $request->hasValidSignature()) {
            return response()->json(['message' => 'Invalid or expired verification link.'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            
            // If phone is also verified, switch status to active
            if ($user->phone_verified_at) {
                $user->update(['status' => 'active']);
            }
        }

        // We can either return JSON, or redirect to a frontend Success Page.
        // For now, as per requirements: redirect to localhost:8000 or return JSON.
        // Since it's a GET request from an email, we'll return a simple HTML success or redirect.
        // To be easily testable via Postman, we can return JSON or HTML.
        return response()->json([
            'message' => 'Email verified successfully!',
        ]);
    }
}
