<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private ?array $credentials = null;

    private string $credentialsPath;

    public function __construct(?string $credentialsPath = null)
    {
        $this->credentialsPath = $credentialsPath ?? base_path('delalabekise-firebase-adminsdk-fbsvc-59dbe459c5_1_1.json');
        if (File::exists($this->credentialsPath)) {
            $this->credentials = json_decode(File::get($this->credentialsPath), true);
        }
    }

    /**
     * Send push notification to a specific device FCM token.
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        return $this->dispatchFcmMessage(['token' => $token], $title, $body, $data);
    }

    /**
     * Send push notification to an FCM topic (e.g., 'admin_alerts').
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): bool
    {
        return $this->dispatchFcmMessage(['topic' => $topic], $title, $body, $data);
    }

    /**
     * Dispatch P1/P2 Operational Alert (replaces Slack/PagerDuty fallback per services_needed.md).
     */
    public function sendCriticalAlert(string $title, string $body, array $metadata = []): void
    {
        Log::critical("OPERATIONAL_P1_ALERT: {$title} - {$body}", $metadata);
        $this->sendToTopic('admin_alerts', "[P1 ALERT] {$title}", $body, array_map('strval', $metadata));
    }

    private function dispatchFcmMessage(array $target, string $title, string $body, array $data = []): bool
    {
        if (app()->environment('testing') || ! $this->credentials) {
            Log::info("FCM Notification (mocked/testing): {$title} - {$body}", array_merge($target, ['data' => $data]));

            return true;
        }

        try {
            $accessToken = $this->getAccessToken();
            if (! $accessToken) {
                Log::warning('FCM: Unable to obtain Google OAuth2 access token.');

                return false;
            }

            $projectId = $this->credentials['project_id'] ?? 'delalabekise';
            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            // All data payload values in FCM HTTP v1 must be strings
            $stringData = [];
            foreach ($data as $key => $value) {
                $stringData[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
            }

            $message = array_merge($target, [
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ]);

            if (! empty($stringData)) {
                $message['data'] = $stringData;
            }

            $response = Http::withToken($accessToken)
                ->timeout(5)
                ->post($url, ['message' => $message]);

            if ($response->successful()) {
                return true;
            }

            Log::error('FCM Send Error: '.$response->body());

            return false;
        } catch (\Throwable $e) {
            Log::error('FCM Dispatch Exception: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get or generate a Google OAuth2 access token using RS256 JWT assertion.
     */
    public function getAccessToken(): ?string
    {
        if (! $this->credentials) {
            return null;
        }

        return Cache::remember('fcm_oauth2_access_token', 3300, function () {
            $now = time();
            $clientEmail = $this->credentials['client_email'];
            $privateKey = $this->credentials['private_key'];

            $header = ['alg' => 'RS256', 'typ' => 'JWT'];
            $claimSet = [
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now,
            ];

            $base64UrlHeader = $this->base64UrlEncode(json_encode($header));
            $base64UrlClaimSet = $this->base64UrlEncode(json_encode($claimSet));
            $dataToSign = $base64UrlHeader.'.'.$base64UrlClaimSet;

            $signature = '';
            if (! openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                Log::error('FCM: OpenSSL sign failed for Google OAuth2 JWT assertion.');

                return null;
            }

            $jwt = $dataToSign.'.'.$this->base64UrlEncode($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error('FCM OAuth2 Exchange Failed: '.$response->body());

            return null;
        });
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
