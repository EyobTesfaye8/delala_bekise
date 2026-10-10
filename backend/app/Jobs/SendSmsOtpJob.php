<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendSmsOtpJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $phone,
        public string $code
    ) {}

    public function handle(): void
    {
        // Integration with EthioTelecom or third-party SMS Gateway (like AfroMessage/Chapa SMS)
        // For now, we log the OTP.
        Log::info("Mock SMS Gateway: Sending OTP {$this->code} to {$this->phone}");
    }
}
