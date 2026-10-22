<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Alert service — sends P1/P2 operational alerts.
 * SRS NFR-OBS-2: uses FCM (since Slack/PagerDuty not available).
 */
class AlertService
{
    public function p1Critical(string $message, array $context = []): void
    {
        Log::critical("[P1-CRITICAL] {$message}", $context);
        // TODO: send FCM push to admin devices when FCM server creds available
    }

    public function p2High(string $message, array $context = []): void
    {
        Log::error("[P2-HIGH] {$message}", $context);
    }

    public function p3Medium(string $message, array $context = []): void
    {
        Log::warning("[P3-MEDIUM] {$message}", $context);
    }
}
