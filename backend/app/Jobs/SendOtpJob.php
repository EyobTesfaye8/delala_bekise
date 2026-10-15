<?php

namespace App\Jobs;

use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Dispatches OTP SMS via the SMS service (with circuit breaker).
 * Runs on the 'high_auth' queue per SRS NFR-PERF-2.
 */
class SendOtpJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        private readonly string $phone,
        private readonly string $code,
    ) {
        $this->onQueue('high_auth');
    }

    public function handle(SmsService $smsService): void
    {
        $message = "Your Delala verification code is: {$this->code}. Valid for 10 minutes. Do not share this code.";

        $smsService->send($this->phone, $message);
    }
}
