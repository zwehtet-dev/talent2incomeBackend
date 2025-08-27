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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Google OAuth authentication service.
    | Get credentials from Google Developer Console.
    |
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/api/auth/google/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Twilio SMS Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Twilio SMS service for phone verification.
    | Optimized for Myanmar phone numbers.
    |
    */
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'), // Your Twilio phone number
        'verify_service_sid' => env('TWILIO_VERIFY_SERVICE_SID'), // Optional: for Twilio Verify API
    ],

    /*
    |--------------------------------------------------------------------------
    | Alternative SMS Providers (for Myanmar)
    |--------------------------------------------------------------------------
    |
    | Alternative SMS providers that have better coverage in Myanmar
    |
    */
    'sms_providers' => [
        'primary' => env('SMS_PRIMARY_PROVIDER', 'twilio'),

        // Local Myanmar SMS provider (example)
        'myanmar_sms' => [
            'api_key' => env('MYANMAR_SMS_API_KEY'),
            'api_secret' => env('MYANMAR_SMS_API_SECRET'),
            'sender_id' => env('MYANMAR_SMS_SENDER_ID', 'Talent2Income'),
            'endpoint' => env('MYANMAR_SMS_ENDPOINT'),
        ],

        // Backup international provider
        'nexmo' => [
            'key' => env('NEXMO_KEY'),
            'secret' => env('NEXMO_SECRET'),
            'from' => env('NEXMO_FROM', 'Talent2Income'),
        ],
    ],

];
