<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Jobs\SendOtpJob;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Handles OTP generation and Guest session creation/recovery.
 * SRS FR-1.1 and FR-1.2.
 */
class OtpController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    /**
     * Send a 6-digit OTP to the given phone number.
     * Rate limited: 60s cooldown, 3/hr per phone, 10/hr per IP.
     */
    public function send(SendOtpRequest $request): JsonResponse
    {
        try {
            $otp = $this->otpService->generate(
                phone: $request->input('phone'),
                purpose: 'auth',
                ip: $request->ip(),
            );
        } catch (\Exception $e) {
            $code = (int) $e->getMessage();

            return match (true) {
                str_contains($e->getMessage(), 'COOLDOWN')  => response()->json([
                    'error' => 'RATE_LIMIT_COOLDOWN',
                    'message' => 'Please wait 60 seconds before requesting another OTP.',
                ], 429),
                str_contains($e->getMessage(), 'PHONE')     => response()->json([
                    'error' => 'RATE_LIMIT_PHONE_HOURLY',
                    'message' => 'Maximum 3 OTP requests per phone number per hour.',
                ], 429),
                str_contains($e->getMessage(), 'IP')        => response()->json([
                    'error' => 'RATE_LIMIT_IP_HOURLY',
                    'message' => 'Too many OTP requests from your network.',
                ], 429),
                default => response()->json(['error' => 'OTP_SEND_FAILED'], 500),
            };
        }

        // Dispatch OTP SMS on high_auth queue
        SendOtpJob::dispatch($request->input('phone'), $otp->code);

        return response()->json([
            'message'    => 'OTP sent successfully.',
            'expires_in' => 600, // 10 minutes
        ]);
    }

    /**
     * Verify OTP and create/recover a Guest session.
     * SRS FR-1.1 (new guest) and FR-1.2 (session recovery).
     */
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->input('phone');
        $code  = $request->input('code');

        $valid = $this->otpService->verify($phone, $code, 'auth');

        if (! $valid) {
            return response()->json([
                'error'   => 'INVALID_OTP',
                'message' => 'The OTP code is invalid or has expired.',
            ], 422);
        }

        // Find existing user by phone (FR-1.2: session recovery)
        $user = User::where('phone', $phone)->first();

        if (! $user) {
            // Create new guest account (FR-1.1)
            $user = User::create([
                'full_name'         => $request->input('full_name', 'Guest User'),
                'phone'             => $phone,
                'type'              => 'guest',
                'status'            => 'active',
                'guest_uuid'        => (string) Str::uuid(),
                'phone_verified_at' => now(),
            ]);
        } else {
            // Mark phone as verified if not already
            $user->update(['phone_verified_at' => now()]);
        }

        if ($user->isSuspended()) {
            return response()->json([
                'error'   => 'ACCOUNT_SUSPENDED',
                'message' => 'Your account has been suspended.',
            ], 403);
        }

        // Revoke any existing guest tokens before issuing a new one
        $user->tokens()->where('name', 'guest-session')->delete();

        // Guest token: 24-hour TTL (SRS §3)
        $expiresAt = Carbon::now()->addHours(24);
        $token     = $user->createToken('guest-session', ['role:guest'], $expiresAt);

        return response()->json([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user'       => [
                'id'       => $user->guest_uuid ?? $user->id,
                'phone'    => $user->phone,
                'type'     => $user->type,
                'status'   => $user->status,
            ],
        ]);
    }
}
