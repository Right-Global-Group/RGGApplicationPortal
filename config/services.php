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

    'resend' => [
        'key' => env('RESEND_KEY'),
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


    'docusign' => [
        'base_url' => env('DOCUSIGN_BASE_URL', 'https://demo.docusign.net/restapi'),
        'account_id' => env('DOCUSIGN_ACCOUNT_ID'),
        'integration_key' => env('DOCUSIGN_INTEGRATION_KEY'),
        'user_id' => env('DOCUSIGN_USER_ID'),
        'private_key' => storage_path('app/docusign/private.key'),
        'webhook_secret' => env('DOCUSIGN_WEBHOOK_SECRET'),
        'auth_url' => env('DOCUSIGN_AUTH_URL', 'https://account-d.docusign.com'),
    ],

    'cardstream' => [
        'contact_email' => env('CARDSTREAM_CONTACT_EMAIL', 'rachel.attwood@g2pay.co.uk'),
        'api_url' => env('CARDSTREAM_API_URL'),
        'api_key' => env('CARDSTREAM_API_KEY'),
    ],

    'acquired' => [
        'api_url' => env('ACQUIRED_API_URL'),
        'api_key' => env('ACQUIRED_API_KEY'),
    ],

];
