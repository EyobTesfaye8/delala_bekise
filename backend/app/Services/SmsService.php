<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * SMS Service with sliding-window circuit breaker.
 *
 * SRS §9.2: If primary AfroMessage failure rate exceeds 15% over 5-min rolling window
 * (min 10 requests), automatically trip breaker and route to secondary provider for 30 minutes.
 */
class SmsService
{
    private const CIRCUIT_KEY = 'sms_circuit_breaker';

    private const WINDOW_SECONDS = 300; // 5 minutes

    private const FAILURE_THRESHOLD = 0.15; // 15%

    private const MIN_REQUESTS = 10;

    private const TRIP_DURATION = 1800; // 30 minutes

    private const PRIMARY = 'afromessage';

    private const SECONDARY = 'secondary';

    /**
     * Send an OTP or generic SMS message.
     */
    public function send(string $phone, string $message): bool
    {
        $provider = $this->resolveProvider();

        try {
            $result = match ($provider) {
                self::PRIMARY   => $this->sendViaAfroMessage($phone, $message),
                self::SECONDARY => $this->sendViaSecondary($phone, $message),
            };

            $this->recordOutcome($provider, success: $result);

            return $result;
        } catch (\Throwable $e) {
            Log::error('SMS send failed', [
                'provider' => $provider,
                'phone'    => '***redacted***',
                'error'    => $e->getMessage(),
            ]);

            $this->recordOutcome($provider, success: false);

            return false;
        }
    }

    private function sendViaAfroMessage(string $phone, string $message): bool
    {
        // AfroMessage API — Ethiopia SMS gateway
        $response = Http::timeout(5)->withHeaders([
            'Authorization' => 'Bearer '.config('services.afromessage.api_key'),
        ])->post('https://api.afromessage.com/api/send', [
            'to'      => $phone,
            'message' => $message,
            'from'    => config('services.afromessage.sender_id', 'Delala'),
        ]);

        if ($response->failed()) {
            Log::warning('AfroMessage API failed', ['status' => $response->status()]);

            return false;
        }

        return true;
    }

    private function sendViaSecondary(string $phone, string $message): bool
    {
        $apiKey = config('services.sms_secondary.api_key');

        if (! $apiKey) {
            Log::error('Secondary SMS provider not configured; message dropped');

            return false;
        }

        // Placeholder for secondary provider — Africa's Talking / Infobip
        $response = Http::timeout(5)->withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
        ])->post(config('services.sms_secondary.endpoint', ''), [
            'to'      => $phone,
            'message' => $message,
        ]);

        return $response->successful();
    }

    // ─────────────────────────── Circuit Breaker Logic ─────────────────────

    private function resolveProvider(): string
    {
        if ($this->isTripped()) {
            Log::notice('SMS circuit breaker tripped; using secondary provider');

            return self::SECONDARY;
        }

        return self::PRIMARY;
    }

    private function isTripped(): bool
    {
        return (bool) Redis::get($this->tripKey());
    }

    private function recordOutcome(string $provider, bool $success): void
    {
        if ($provider !== self::PRIMARY) {
            return; // Only track primary provider for circuit logic
        }

        $redis = Redis::connection();
        $now   = time();
        $key   = $this->windowKey();

        // Add outcome to sorted set (score = timestamp, member = unique ID + outcome)
        $member = $now.':'.($success ? '1' : '0').':'.uniqid();
        $redis->zadd($key, $now, $member);

        // Remove entries older than 5 minutes
        $redis->zremrangebyscore($key, '-inf', $now - self::WINDOW_SECONDS);
        $redis->expire($key, self::WINDOW_SECONDS);

        // Evaluate circuit breaker
        $allMembers      = $redis->zrange($key, 0, -1);
        $totalRequests   = count($allMembers);
        $failedRequests  = count(array_filter($allMembers, fn ($m) => str_contains($m, ':0:')));

        if ($totalRequests >= self::MIN_REQUESTS) {
            $failureRate = $failedRequests / $totalRequests;

            if ($failureRate >= self::FAILURE_THRESHOLD) {
                // Trip the circuit breaker for 30 minutes
                Redis::setex($this->tripKey(), self::TRIP_DURATION, '1');

                Log::critical('SMS circuit breaker TRIPPED — switching to secondary', [
                    'failure_rate'   => round($failureRate * 100, 2).'%',
                    'total_requests' => $totalRequests,
                    'failed'         => $failedRequests,
                ]);
            }
        }
    }

    private function windowKey(): string
    {
        return 'sms_cb_window';
    }

    private function tripKey(): string
    {
        return 'sms_cb_tripped';
    }
}
