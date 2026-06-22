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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'mailtrap' => [
        'api_token' => env('MAILTRAP_API_TOKEN'),
        'account_id' => env('MAILTRAP_ACCOUNT_ID'),
        'base_url' => env('MAILTRAP_API_BASE_URL', 'https://mailtrap.io/api'),
        'timeout' => (int) env('MAILTRAP_API_TIMEOUT', 10),
    ],

    'email_inbound' => [
        'provider' => env('EMAIL_INBOUND_PROVIDER', 'mailtrap'),
        'webhook_token' => env('EMAIL_INBOUND_WEBHOOK_TOKEN', env('MAILTRAP_INBOUND_WEBHOOK_TOKEN')),
        'imap' => [
            'enabled' => filter_var(env('EMAIL_INBOUND_IMAP_ENABLED', false), FILTER_VALIDATE_BOOL),
            'host' => env('EMAIL_INBOUND_IMAP_HOST'),
            'port' => (int) env('EMAIL_INBOUND_IMAP_PORT', 993),
            'encryption' => env('EMAIL_INBOUND_IMAP_ENCRYPTION', 'ssl'),
            'username' => env('EMAIL_INBOUND_IMAP_USERNAME'),
            'password' => env('EMAIL_INBOUND_IMAP_PASSWORD'),
            'folder' => env('EMAIL_INBOUND_IMAP_FOLDER', 'INBOX'),
        ],
    ],

    'email_delivery' => [
        'provider' => env('EMAIL_DELIVERY_PROVIDER', env('EMAIL_INBOUND_PROVIDER', 'brevo')),
        'webhook_token' => env('EMAIL_DELIVERY_WEBHOOK_TOKEN', env('EMAIL_INBOUND_WEBHOOK_TOKEN')),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => filter_var(env('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOL),
    ],

];
