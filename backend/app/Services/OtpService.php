<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * OTP Service — generates, stores, and verifies 6-digit OTP codes.
 *
 * Rate limits enforced here (60-second cooldown, 3/hr per phone, 10/hr per IP).
 * SRS NFR-SEC-1.
 */
class OtpService
{
    private const OTP_TTL_MINUTES = 10;

    private const COOLDOWN_SECONDS = 60;

    private const MAX_PER_PHONE_PER_HOUR = 3;

    private const MAX_PER_IP_PER_HOUR = 10;

    /**
     * @throws \Exception When rate limit exceeded
     */
    public function generate(string $phone, string $purpose, string $ip): OtpCode
    {
        $this->enforceRateLimits($phone, $ip);

        // Invalidate previous unused OTPs for this phone+purpose
        OtpCode::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]); // expire them immediately

        $code = (string) random_int(100000, 999999);

        $otp = OtpCode::create([
            'phone'      => $phone,
            'code'       => $code,
            'purpose'    => $purpose,
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'ip_address' => $ip,
        ]);

        // Set cooldown key in Redis
        Redis::setex($this->cooldownKey($phone), self::COOLDOWN_SECONDS, '1');

        return $otp;
    }

    /**
     * Verify an OTP code. Returns true and marks it used on success.
     */
    public function verify(string $phone, string $code, string $purpose): bool
    {
        $otp = OtpCode::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otp || ! hash_equals($otp->code, $code)) {
            return false;
        }

        $otp->update(['used_at' => now()]);

        return true;
    }

    /**
     * @throws \Exception
     */
    private function enforceRateLimits(string $phone, string $ip): void
    {
        // 60-second cooldown per phone
        if (Redis::exists($this->cooldownKey($phone))) {
            throw new \Exception('RATE_LIMIT_COOLDOWN', 429);
        }

        // 3 requests per phone per hour
        $phoneSent = OtpCode::query()
            ->where('phone', $phone)
            ->where('created_at', '>', Carbon::now()->subHour())
            ->count();

        if ($phoneSent >= self::MAX_PER_PHONE_PER_HOUR) {
            throw new \Exception('RATE_LIMIT_PHONE_HOURLY', 429);
        }

        // 10 requests per IP per hour
        $ipSent = OtpCode::query()
            ->where('ip_address', $ip)
            ->where('created_at', '>', Carbon::now()->subHour())
            ->count();

        if ($ipSent >= self::MAX_PER_IP_PER_HOUR) {
            throw new \Exception('RATE_LIMIT_IP_HOURLY', 429);
        }
    }

    private function cooldownKey(string $phone): string
    {
        return 'otp_cooldown:'.md5($phone);
    }
}
