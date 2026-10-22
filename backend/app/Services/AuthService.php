<?php

namespace App\Services;

use App\Jobs\SendSmsOtpJob;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthService
{
    /**
     * Normalize phone number to international format (+251)
     */
    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', $phone);
        if (str_starts_with($phone, '09') || str_starts_with($phone, '07')) {
            return '+251'.substr($phone, 1);
        }
        if (str_starts_with($phone, '251')) {
            return '+'.$phone;
        }

        return $phone;
    }

    /**
     * Generate an OTP for a given phone number.
     */
    public function generateOtp(string $phone, string $purpose = 'auth'): OtpCode
    {
        // Expire any existing valid OTPs for this phone/purpose
        OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]); // mark as used/invalidated

        // Generate a 6-digit OTP
        // In a real app, use random_int, for dev/testing we might hardcode or use simple random
        $code = (app()->environment(['local', 'testing'])) ? '123456' : str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        $otp = OtpCode::create([
            'phone' => $phone,
            'code' => $code,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(10), // valid for 10 minutes
            'ip_address' => request()->ip(),
        ]);

        // Dispatch a job to send the SMS via the SMS Gateway
        dispatch(new SendSmsOtpJob($phone, $code));

        return $otp;
    }

    /**
     * Validate an OTP and optionally create/login the user.
     */
    public function validateOtp(string $phone, string $code, string $purpose = 'auth'): bool
    {
        $otp = OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('code', $code)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $otp) {
            return false;
        }

        $otp->update(['used_at' => now()]);

        return true;
    }

    /**
     * Authenticate or create a guest buyer.
     */
    public function authenticateGuest(string $phone): User
    {
        return DB::transaction(function () use ($phone) {
            $user = User::where('phone', $phone)->first();

            if (! $user) {
                // New Guest User
                $user = User::create([
                    'phone' => $phone,
                    'type' => 'guest',
                    'guest_uuid' => (string) Str::uuid(),
                    'phone_verified_at' => now(),
                ]);
            } else {
                // Existing user logging in via OTP
                if (! $user->phone_verified_at) {
                    $user->phone_verified_at = now();

                    if ($user->hasVerifiedEmail() && $user->status === 'unverified') {
                        $user->status = 'active';
                    }

                    $user->save();
                }
            }

            return $user;
        });
    }
}
