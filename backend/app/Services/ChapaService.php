<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Chapa Payment Gateway service.
 * SRS §9.2, NFR-SEC-4.
 */
class ChapaService
{
    private const BASE_URL = 'https://api.chapa.co/v1';
    private const TIMEOUT  = 5; // seconds — SRS §9.2 circuit breaker threshold

    private string $secretKey;
    private string $webhookSecret;

    public function __construct()
    {
        $this->secretKey     = config('services.chapa.secret_key');
        $this->webhookSecret = config('services.chapa.webhook_secret');
    }

    /**
     * Initialize a Chapa checkout session.
     * SRS §9.2: If HTTP 5xx or timeout, abort DB transaction and return 503.
     *
     * @return array{checkout_url: string, tx_ref: string}
     * @throws \Exception on gateway failure
     */
    public function initializePayment(
        int $amountSantim,
        string $buyerEmail,
        string $buyerName,
        string $buyerPhone,
        string $returnUrl,
        string $callbackUrl,
        array $metadata = [],
    ): array {
        $txRef  = 'DLK-'.Str::upper(Str::random(16));
        $amount = $amountSantim / 100; // Chapa accepts ETB (decimal)

        $response = Http::timeout(self::TIMEOUT)
            ->withToken($this->secretKey)
            ->post(self::BASE_URL.'/transaction/initialize', [
                'amount'          => number_format($amount, 2, '.', ''),
                'currency'        => 'ETB',
                'email'           => $buyerEmail,
                'first_name'      => explode(' ', $buyerName)[0],
                'last_name'       => implode(' ', array_slice(explode(' ', $buyerName), 1)) ?: '-',
                'phone_number'    => $buyerPhone,
                'tx_ref'          => $txRef,
                'return_url'      => $returnUrl,
                'callback_url'    => $callbackUrl,
                'customization'   => [
                    'title'       => 'Delala Commission Escrow',
                    'description' => 'Commission escrow for your Delala transaction',
                ],
                'meta'            => $metadata,
            ]);

        if ($response->failed()) {
            Log::error('Chapa initialization failed', [
                'status'   => $response->status(),
                'response' => $response->json(),
            ]);
            throw new \Exception('PAYMENT_GATEWAY_UNAVAILABLE', 503);
        }

        $data = $response->json();

        return [
            'checkout_url' => $data['data']['checkout_url'],
            'tx_ref'       => $txRef,
        ];
    }

    /**
     * Verify a transaction via Chapa's verify endpoint.
     * Used by the active polling fallback job (SRS §9.2).
     */
    public function verifyTransaction(string $txRef): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withToken($this->secretKey)
                ->get(self::BASE_URL."/transaction/verify/{$txRef}");

            if ($response->failed()) {
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::warning('Chapa verify failed', ['tx_ref' => $txRef, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Initiate a refund via Chapa.
     * SRS §9.2: On failure → set REFUND_FAILED, do NOT execute JNL-3, queue retries.
     *
     * @throws \Exception on refund failure
     */
    public function initiateRefund(string $txRef, int $amountSantim, string $reason = ''): bool
    {
        $response = Http::timeout(self::TIMEOUT)
            ->withToken($this->secretKey)
            ->post(self::BASE_URL.'/refund', [
                'tx_ref'  => $txRef,
                'amount'  => number_format($amountSantim / 100, 2, '.', ''),
                'reason'  => $reason ?: 'Escrow refund',
            ]);

        if ($response->serverError()) {
            Log::error('Chapa refund API failed', [
                'tx_ref'         => $txRef,
                'amount_santim'  => $amountSantim,
                'status'         => $response->status(),
            ]);
            throw new \Exception('REFUND_GATEWAY_FAILED', 502);
        }

        return $response->successful();
    }

    /**
     * Verify Chapa webhook HMAC signature.
     * SRS NFR-SEC-4: hash_hmac SHA-256 via timing-safe hash_equals().
     */
    public function verifyWebhookSignature(string $rawPayload, string $receivedSignature): bool
    {
        $expectedSignature = hash_hmac('sha256', $rawPayload, $this->webhookSecret);

        return hash_equals($expectedSignature, $receivedSignature);
    }
}
