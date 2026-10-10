<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
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
        $message = "Your DelalaBekise authentication OTP is: {$this->code}. It is valid for 5 minutes. Do not share it with anyone.";
        
        $response = Http::withToken(env('AFROMESSAGE_API_KEY'))
            ->post('https://api.afromessage.com/api/send', [
                'to' => $this->phone,
                'message' => $message,
            ]);

        $json = $response->json();

        if ($response->failed() || ($json['acknowledge'] ?? '') === 'error') {
            Log::error("AfroMessage SMS Failed: " . $response->body());
        } else {
            Log::info("AfroMessage SMS Sent to {$this->phone}.");
        }
    }
}
