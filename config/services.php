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

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    'msg91' => [
        'auth_key' => env('MSG91_AUTH_KEY'),
        'otp_template_id' => env('MSG91_OTP_TEMPLATE_ID'),
        'default_country_code' => env('MSG91_DEFAULT_COUNTRY_CODE', '91'),
        'otp_expiry_minutes' => env('MSG91_OTP_EXPIRY_MINUTES', 10),
        'max_resend_attempts' => env('MSG91_MAX_RESEND_ATTEMPTS', 3),
        'resend_window_minutes' => env('MSG91_RESEND_WINDOW_MINUTES', 30),
        'verify_with_provider' => env('MSG91_VERIFY_WITH_PROVIDER', false),
    ],

    'demo_otp' => [
        'enabled' => env('DEMO_OTP_ENABLED', false),
        'code' => env('DEMO_OTP_CODE', '123456'),
        'phones' => array_filter(array_map('trim', explode(',', env('DEMO_OTP_PHONES', '9999999999')))),
    ],

];
