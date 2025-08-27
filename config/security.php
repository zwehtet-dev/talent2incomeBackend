<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains security-specific configuration options including
    | CORS settings, content security policy, rate limiting, and other
    | security measures for the Talent2Income platform.
    |
    */

    'cors' => [
        'paths' => ['api/*', 'sanctum/csrf-cookie'],
        'allowed_methods' => ['*'],
        'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000') ?: 'http://localhost:3000'),
        'allowed_origins_patterns' => [],
        'allowed_headers' => ['*'],
        'exposed_headers' => [],
        'max_age' => 0,
        'supports_credentials' => true,
    ],

    'content_security_policy' => [
        'enabled' => env('CSP_ENABLED', false),
        'report_only' => env('CSP_REPORT_ONLY', true),
        'directives' => [
            'default-src' => "'self'",
            'script-src' => "'self' 'unsafe-inline' 'unsafe-eval'",
            'style-src' => "'self' 'unsafe-inline'",
            'img-src' => "'self' data: https:",
            'font-src' => "'self' data:",
            'connect-src' => "'self'",
            'media-src' => "'self'",
            'object-src' => "'none'",
            'child-src' => "'self'",
            'frame-ancestors' => "'none'",
            'form-action' => "'self'",
            'upgrade-insecure-requests' => true,
        ],
    ],

    'headers' => [
        'x_frame_options' => 'DENY',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'permissions_policy' => [
            'camera' => '()',
            'microphone' => '()',
            'geolocation' => '(self)',
            'payment' => '(self)',
        ],
    ],

    'rate_limiting' => [
        'global' => [
            'requests' => env('THROTTLE_REQUESTS_PER_MINUTE', 60),
            'per_minute' => 1,
        ],
        'auth' => [
            'login_attempts' => env('THROTTLE_LOGIN_ATTEMPTS', 5),
            'decay_minutes' => env('THROTTLE_LOGIN_DECAY_MINUTES', 1),
            'lockout_duration' => env('THROTTLE_LOCKOUT_DURATION', 60), // seconds
        ],
        'api' => [
            'authenticated' => env('THROTTLE_API_AUTHENTICATED', 100),
            'unauthenticated' => env('THROTTLE_API_UNAUTHENTICATED', 20),
        ],
        'password_reset' => [
            'attempts' => 3,
            'decay_minutes' => 60,
        ],
    ],

    'encryption' => [
        'algorithm' => 'AES-256-CBC',
        'key_rotation_days' => 90,
        'backup_keys_count' => 3,
    ],

    'session_security' => [
        'regenerate_on_login' => true,
        'invalidate_on_logout' => true,
        'timeout_minutes' => env('SESSION_LIFETIME', 120),
        'concurrent_sessions' => env('MAX_CONCURRENT_SESSIONS', 3),
    ],

    'password_policy' => [
        'min_length' => 8,
        'max_length' => 128,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => false,
        'prevent_common_passwords' => true,
        'prevent_personal_info' => true,
        'history_count' => 5, // Remember last 5 passwords
        'expiry_days' => 90,
    ],

    'two_factor_auth' => [
        'enabled' => env('2FA_ENABLED', false),
        'required_for_admin' => env('2FA_REQUIRED_ADMIN', true),
        'backup_codes_count' => 8,
        'recovery_codes_count' => 10,
        'window' => 1, // 30-second windows
    ],

    'ip_filtering' => [
        'enabled' => env('IP_FILTERING_ENABLED', false),
        'whitelist' => explode(',', env('IP_WHITELIST', '') ?: ''),
'blacklist' => explode(',', env('IP_BLACKLIST', '') ?: ''),
'admin_whitelist' => explode(',', env('ADMIN_IP_WHITELIST', '') ?: ''),
    ],

    'audit_logging' => [
        'enabled' => env('AUDIT_LOGGING_ENABLED', true),
        'log_successful_logins' => true,
        'log_failed_logins' => true,
        'log_password_changes' => true,
        'log_permission_changes' => true,
        'log_sensitive_operations' => true,
        'retention_days' => 365,
    ],

    'file_upload_security' => [
        'scan_for_viruses' => env('VIRUS_SCANNING_ENABLED', false),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'quarantine_suspicious_files' => true,
    ],

    'api_security' => [
        'require_https' => env('REQUIRE_HTTPS', false),
        'validate_content_type' => true,
        'max_request_size' => 50 * 1024 * 1024, // 50MB
        'timeout_seconds' => 30,
    ],
];
