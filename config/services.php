<?php

return [
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sms' => [
        'url' => env('SMS_PROVIDER_URL'),
        'api_key' => env('SMS_PROVIDER_API_KEY'),
        'sender_id' => env('SMS_PROVIDER_SENDER_ID'),
        'timeout' => env('SMS_PROVIDER_TIMEOUT', 30),
    ],

    'otp' => [
        'length' => 6,
        'expiry' => 10, // minutes
        'max_attempts' => 3,
        'throttle' => 60, // seconds
    ],

    's3' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'throw' => true,
    ],

    'tenancy' => [
        'database' => [
            'prefix' => 'tenant',
            'suffix' => '',
        ],
        'cache' => [
            'prefix' => 'tenant',
        ],
    ],
];
