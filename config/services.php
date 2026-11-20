<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
    ],

    // Shared secret for verifying HMAC-SHA256 signatures on OCR webhook callbacks.
    // Must match the secret configured on the OCR microservice / worker side.
    'ocr' => [
        'webhook_secret' => env('OCR_WEBHOOK_SECRET'),
        'default_language' => env('OCR_DEFAULT_LANGUAGE', 'eng+fil'),
        'process_timeout' => (int) env('OCR_PROCESS_TIMEOUT', 300),
        'pdf_render_timeout' => (int) env('OCR_PDF_RENDER_TIMEOUT', 300),
        'pdf_dpi' => (int) env('OCR_PDF_DPI', 300),
        'oem' => (int) env('OCR_OEM', 3),
        'psm' => (int) env('OCR_PSM', 3),
        'fallback_psm' => env('OCR_FALLBACK_PSM', '6,11'),
        'preprocess_enabled' => filter_var(env('OCR_PREPROCESS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'preprocess_min_width' => (int) env('OCR_PREPROCESS_MIN_WIDTH', 1800),
    ],

];
