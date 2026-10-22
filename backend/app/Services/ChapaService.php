<?php

namespace App\Services;

use App\Models\EscrowTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChapaService
{
    private string $secretKey;

    private string $publicKey;

    private string $baseUrl = 'https://api.chapa.co/v1';

    public function __construct()
    {
        $this->secretKey = config('services.chapa.secret_key', env('CHAPA_PRIVATE_KEY'));
        $this->publicKey = config('services.chapa.public_key', env('CHAPA_PUBLIC_KEY'));
    }

    /**
     * Generate a Chapa checkout URL for an escrow transaction.
     */
    public function generateCheckoutUrl(EscrowTransaction $escrow): ?string
    {
        $txRef = 'TX_'.$escrow->uuid.'_'.time();

        // Convert santim to ETB for Chapa API
        $amountEtb = $escrow->commission_amount_santim / 100;

        $response = Http::withToken($this->secretKey)
            ->timeout(10) // NFR-4.1 API Latency / Failure mitigation
            ->post("{$this->baseUrl}/transaction/initialize", [
                'amount' => $amountEtb,
                'currency' => 'ETB',
                'email' => $escrow->buyer->email ?? 'buyer@delala.local',
                'first_name' => $escrow->buyer->full_name ?? 'Buyer',
                'tx_ref' => $txRef,
                'callback_url' => url('/api/v1/escrow/chapa-callback'),
                'return_url' => url('/checkout/success'),
                'customization' => [
                    'title' => 'Delala Escrow Payment',
                    'description' => 'Escrow payment for job: '.$escrow->job->title,
                ],
            ]);

        if ($response->successful() && $response->json('status') === 'success') {
            $escrow->update([
                'chapa_tx_ref' => $txRef,
                'chapa_checkout_url' => $response->json('data.checkout_url'),
            ]);

            return $response->json('data.checkout_url');
        }

        Log::error('Chapa checkout URL generation failed', [
            'escrow_id' => $escrow->id,
            'response' => $response->json(),
            'status' => $response->status(),
        ]);

        return null; // Signals 503 to the controller
    }

    /**
     * Trigger Chapa Refund API.
     */
    public function refund(EscrowTransaction $escrow): bool
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/refund", [
                'tx_ref' => $escrow->chapa_tx_ref,
                // Refund full amount. Chapa fee is absorbed by platform.
            ]);

        if ($response->successful() && $response->json('status') === 'success') {
            return true;
        }

        Log::error('Chapa refund API failed', [
            'escrow_id' => $escrow->id,
            'tx_ref' => $escrow->chapa_tx_ref,
            'response' => $response->json(),
            'status' => $response->status(),
        ]);

        return false;
    }

    /**
     * Query Chapa verification API directly (circuit breaker / polling fallback).
     */
    public function verifyTransaction(string $txRef): ?array
    {
        $response = Http::withToken($this->secretKey)
            ->timeout(10)
            ->get("{$this->baseUrl}/transaction/verify/{$txRef}");

        if ($response->successful() && $response->json('status') === 'success') {
            return $response->json('data');
        }

        return null;
    }

    /**
     * Verify the Chapa webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $calculatedSignature = hash_hmac('sha256', $payload, $this->secretKey);

        return hash_equals($calculatedSignature, $signature);
    }
}
