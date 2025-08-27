<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enterprise Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains enterprise-specific configuration options for the
    | Talent2Income platform including security, monitoring, and performance
    | settings that are specific to enterprise deployments.
    |
    */

    'security' => [
        'rate_limiting' => [
            'api_requests_per_minute' => env('THROTTLE_REQUESTS_PER_MINUTE', 60),
            'login_attempts' => env('THROTTLE_LOGIN_ATTEMPTS', 5),
            'login_decay_minutes' => env('THROTTLE_LOGIN_DECAY_MINUTES', 1),
        ],

        'session' => [
            'secure_cookies' => env('SESSION_SECURE_COOKIE', false),
            'http_only' => env('SESSION_HTTP_ONLY', true),
            'same_site' => env('SESSION_SAME_SITE', 'lax'),
            'encrypt' => env('SESSION_ENCRYPT', true),
        ],

        'password' => [
            'bcrypt_rounds' => env('BCRYPT_ROUNDS', 12),
            'min_length' => 8,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_numbers' => true,
            'require_symbols' => false,
        ],
    ],

    'monitoring' => [
        'telescope_enabled' => env('TELESCOPE_ENABLED', false),
        'sentry_dsn' => env('SENTRY_LARAVEL_DSN'),
        'log_retention_days' => env('LOG_DAILY_DAYS', 30),
        'performance_logging' => env('PERFORMANCE_LOGGING', true),
    ],

    'database' => [
        'read_write_splitting' => env('DB_READ_WRITE_SPLITTING', false),
        'connection_timeout' => 30,
        'query_timeout' => 60,
        'slow_query_threshold' => 1000, // milliseconds
    ],

    'cache' => [
        'default_ttl' => env('CACHE_TTL', 3600),
        'prefix' => env('CACHE_PREFIX', 'talent2income'),
        'tags_enabled' => true,
    ],

    'queue' => [
        'default_timeout' => 300, // 5 minutes
        'retry_after' => 90,
        'max_attempts' => 3,
        'batch_size' => 100,
    ],

    'api' => [
        'version' => 'v1',
        'pagination' => [
            'default_per_page' => 15,
            'max_per_page' => 100,
        ],
        'response_format' => [
            'include_meta' => true,
            'include_links' => true,
            'wrap_data' => true,
        ],
    ],

    'payments' => [
        'platform_fee_percentage' => 5.0,
        'escrow_hold_days' => 7,
        'dispute_resolution_days' => 30,
        'supported_currencies' => ['USD', 'EUR', 'GBP'],
        'minimum_transaction_amount' => 5.00,
    ],

    'file_uploads' => [
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'virus_scanning' => env('VIRUS_SCANNING_ENABLED', false),
    ],

    'search' => [
        'driver' => env('SCOUT_DRIVER', 'database'),
        'results_per_page' => 20,
        'max_results' => 1000,
        'highlight_enabled' => true,
    ],

    'notifications' => [
        'channels' => ['mail', 'database'],
        'queue_notifications' => true,
        'batch_notifications' => true,
        'rate_limiting' => [
            'email_per_hour' => 10,
            'sms_per_day' => 5,
        ],
    ],
];
