<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Verification Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SMS verification service, specifically optimized
    | for Myanmar phone numbers and local regulations.
    |
    */

    // Verification code settings
    'verification_code_length' => env('SMS_VERIFICATION_CODE_LENGTH', 6),
    'code_expiry_minutes' => env('SMS_CODE_EXPIRY_MINUTES', 10),

    // Security settings
    'max_attempts' => env('SMS_MAX_ATTEMPTS', 3),
    'lockout_minutes' => env('SMS_LOCKOUT_MINUTES', 30),
    'rate_limit_minutes' => env('SMS_RATE_LIMIT_MINUTES', 2),

    // Myanmar specific settings
    'myanmar_country_code' => '+95',
    'supported_operators' => [
        'telenor' => ['prefix' => ['097', '096'], 'name' => 'Telenor Myanmar'],
        'ooredoo' => ['prefix' => ['099', '098'], 'name' => 'Ooredoo Myanmar'],
        'mpt' => ['prefix' => ['092', '093', '094', '095'], 'name' => 'MPT'],
        'mytel' => ['prefix' => ['096'], 'name' => 'Mytel'],
    ],

    // Message templates
    'templates' => [
        'verification' => [
            'en' => 'Your Talent2Income verification code is: {code}. This code will expire in {minutes} minutes. Do not share this code with anyone.',
            'my' => 'သင့်ရဲ့ Talent2Income အတည်ပြုကုဒ်မှာ: {code} ဖြစ်ပါတယ်။ ဒီကုဒ်ဟာ {minutes} မိနစ်အတွင်း သက်တမ်းကုန်ဆုံးပါမယ်။ ဒီကုဒ်ကို မည်သူ့ကိုမှ မျှဝေပေးရန် မလိုအပ်ပါ။',
        ],
        'welcome' => [
            'en' => 'Welcome to Talent2Income! Your phone number has been verified successfully.',
            'my' => 'Talent2Income မှ ကြိုဆိုပါတယ်! သင့်ဖုန်းနံပါတ် အတည်ပြုပြီးပါပြီ။',
        ],
    ],

    // Compliance settings for Myanmar
    'compliance' => [
        'require_consent' => env('SMS_REQUIRE_CONSENT', true),
        'store_consent_record' => env('SMS_STORE_CONSENT_RECORD', true),
        'consent_expiry_days' => env('SMS_CONSENT_EXPIRY_DAYS', 365),
        'opt_out_keywords' => ['STOP', 'UNSUBSCRIBE', 'QUIT', 'CANCEL'],
        'privacy_notice' => 'By providing your phone number, you consent to receive SMS messages from Talent2Income for verification and important account updates.',
    ],

    // Logging and monitoring
    'logging' => [
        'enabled' => env('SMS_LOGGING_ENABLED', true),
        'log_channel' => env('SMS_LOG_CHANNEL', 'sms'),
        'log_sensitive_data' => env('SMS_LOG_SENSITIVE_DATA', false),
        'monitor_delivery_status' => env('SMS_MONITOR_DELIVERY', true),
    ],

    // Cost optimization
    'cost_optimization' => [
        'use_local_gateway' => env('SMS_USE_LOCAL_GATEWAY', false),
        'fallback_to_international' => env('SMS_FALLBACK_INTERNATIONAL', true),
        'preferred_route' => env('SMS_PREFERRED_ROUTE', 'premium'), // premium, standard, promotional
    ],

    // Testing and development
    'testing' => [
        'mock_sms_in_testing' => env('SMS_MOCK_IN_TESTING', true),
        'test_phone_numbers' => [
            '+959123456789', // Test numbers that won't actually send SMS
            '+959987654321',
        ],
        'always_use_test_code' => env('SMS_ALWAYS_USE_TEST_CODE', false),
        'test_code' => env('SMS_TEST_CODE', '123456'),
    ],
];
