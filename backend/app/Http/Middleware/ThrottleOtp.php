<?php

namespace App\Http\Middleware;

use App\Models\OtpCode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Additional OTP throttle middleware applied to the send-otp route.
 * Doubles as a last-resort guard even if the request bypasses SendOtpRequest validation.
 * SRS NFR-SEC-1.
 */
class ThrottleOtp
{
    public function handle(Request $request, Closure $next): Response
    {
        $phone = $request->input('phone');

        if (! $phone) {
            return $next($request);
        }

        // 60-second cooldown check
        if (Redis::exists('otp_cooldown:'.md5($phone))) {
            return response()->json([
                'error' => 'RATE_LIMIT_COOLDOWN',
                'message' => 'Please wait 60 seconds before requesting another OTP.',
            ], 429);
        }

        // 3 per phone per hour check
        $phoneSent = OtpCode::where('phone', $phone)
            ->where('created_at', '>', Carbon::now()->subHour())
            ->count();

        if ($phoneSent >= 3) {
            return response()->json([
                'error' => 'RATE_LIMIT_PHONE_HOURLY',
                'message' => 'Maximum 3 OTP requests per phone number per hour.',
            ], 429);
        }

        // 10 per IP per hour
        $ip = $request->ip();
        $ipSent = OtpCode::where('ip_address', $ip)
            ->where('created_at', '>', Carbon::now()->subHour())
            ->count();

        if ($ipSent >= 10) {
            return response()->json([
                'error' => 'RATE_LIMIT_IP_HOURLY',
                'message' => 'Too many OTP requests from your network.',
            ], 429);
        }

        return $next($request);
    }
}
