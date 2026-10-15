<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // ─── AfroMessage SMS (Primary Provider) ──────────────────────────────
    'afromessage' => [
        'api_key' => env('AFROMESSAGE_API_KEY'),
        'sender_id' => env('AFROMESSAGE_SENDER_ID', 'Delala'),
        'endpoint' => 'https://api.afromessage.com/api/send',
    ],

    // ─── Chapa Payment Gateway ────────────────────────────────────────────
    'chapa' => [
        'public_key' => env('CHAPA_PUBLIC_KEY'),
        'secret_key' => env('CHAPA_PRIVATE_KEY'),
        'webhook_secret' => env('CHAPA_WEBHOOK_SECRET', 'delala_dummy_webhook_secret_change_in_prod'),
        'base_url' => env('CHAPA_BASE_URL', 'https://api.chapa.co/v1'),
    ],

    // ─── Firebase (FCM Push + Alerting) ──────────────────────────────────
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'api_key' => env('FIREBASE_API_KEY'),
        'credentials_json' => env('FIREBASE_CREDENTIALS_JSON'),
        'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID'),
        'app_id' => env('FIREBASE_APP_ID'),
    ],

    // ─── Google reCAPTCHA v3 ──────────────────────────────────────────────
    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

    // ─── Google OAuth ─────────────────────────────────────────────────────
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

];
