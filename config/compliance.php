<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Data Retention Periods
    |--------------------------------------------------------------------------
    |
    | Define how long different types of data should be retained in days.
    | These values are used by the compliance cleanup commands.
    |
    */
    'retention_periods' => [
        'audit_logs' => env('RETENTION_AUDIT_LOGS', 2555), // 7 years
        'sensitive_audit_logs' => env('RETENTION_SENSITIVE_AUDIT_LOGS', 3650), // 10 years
        'user_data' => env('RETENTION_USER_DATA', 2555), // 7 years after account deletion
        'payment_data' => env('RETENTION_PAYMENT_DATA', 2555), // 7 years
        'message_data' => env('RETENTION_MESSAGE_DATA', 1095), // 3 years
        'session_data' => env('RETENTION_SESSION_DATA', 30), // 30 days
        'security_incidents' => env('RETENTION_SECURITY_INCIDENTS', 2555), // 7 years
        'gdpr_requests' => env('RETENTION_GDPR_REQUESTS', 365), // 1 year for completed requests
        'user_consents' => env('RETENTION_USER_CONSENTS', 2555), // 7 years
    ],

    /*
    |--------------------------------------------------------------------------
    | GDPR Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for GDPR compliance features.
    |
    */
    'gdpr' => [
        'export_expiry_days' => env('GDPR_EXPORT_EXPIRY_DAYS', 30),
        'request_verification_expiry_hours' => env('GDPR_REQUEST_VERIFICATION_EXPIRY_HOURS', 72),
        'max_export_file_size' => env('GDPR_MAX_EXPORT_FILE_SIZE', 100 * 1024 * 1024), // 100MB
        'allowed_request_types' => [
            'export',
            'delete',
            'rectify',
            'restrict',
            'object',
        ],
        'required_consents' => [
            'privacy_policy',
            'terms_of_service',
        ],
        'optional_consents' => [
            'marketing',
            'cookies',
            'data_processing',
            'third_party_sharing',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for audit logging.
    |
    */
    'audit' => [
        'enabled' => env('AUDIT_ENABLED', true),
        'log_sensitive_data' => env('AUDIT_LOG_SENSITIVE_DATA', false),
        'integrity_checking' => env('AUDIT_INTEGRITY_CHECKING', true),
        'batch_size' => env('AUDIT_BATCH_SIZE', 1000),
        'excluded_events' => [
            'model.retrieved', // Too noisy
            'cache.hit',
            'cache.miss',
        ],
        'sensitive_models' => [
            'App\Models\User',
            'App\Models\Payment',
            'App\Models\GdprRequest',
            'App\Models\UserConsent',
            'App\Models\SecurityIncident',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Incident Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for security incident management.
    |
    */
    'security' => [
        'auto_resolve_false_positives' => env('SECURITY_AUTO_RESOLVE_FALSE_POSITIVES', true),
        'false_positive_threshold_days' => env('SECURITY_FALSE_POSITIVE_THRESHOLD_DAYS', 7),
        'critical_incident_notification' => env('SECURITY_CRITICAL_INCIDENT_NOTIFICATION', true),
        'incident_retention_days' => env('SECURITY_INCIDENT_RETENTION_DAYS', 2555), // 7 years
        'max_incidents_per_ip' => env('SECURITY_MAX_INCIDENTS_PER_IP', 100),
        'brute_force_threshold' => env('SECURITY_BRUTE_FORCE_THRESHOLD', 5),
        'suspicious_activity_threshold' => env('SECURITY_SUSPICIOUS_ACTIVITY_THRESHOLD', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for automated cleanup processes.
    |
    */
    'cleanup' => [
        'enabled' => env('COMPLIANCE_CLEANUP_ENABLED', true),
        'schedule' => env('COMPLIANCE_CLEANUP_SCHEDULE', 'daily'),
        'batch_size' => env('COMPLIANCE_CLEANUP_BATCH_SIZE', 1000),
        'max_execution_time' => env('COMPLIANCE_CLEANUP_MAX_EXECUTION_TIME', 3600), // 1 hour
        'notification_email' => env('COMPLIANCE_NOTIFICATION_EMAIL'),
        'dry_run_mode' => env('COMPLIANCE_DRY_RUN_MODE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Consent Management
    |--------------------------------------------------------------------------
    |
    | Configuration for user consent management.
    |
    */
    'consent' => [
        'current_privacy_policy_version' => env('PRIVACY_POLICY_VERSION', '1.0'),
        'current_terms_version' => env('TERMS_OF_SERVICE_VERSION', '1.0'),
        'consent_expiry_days' => env('CONSENT_EXPIRY_DAYS', 365), // 1 year
        'require_explicit_consent' => env('REQUIRE_EXPLICIT_CONSENT', true),
        'allow_consent_withdrawal' => env('ALLOW_CONSENT_WITHDRAWAL', true),
        'consent_methods' => [
            'checkbox',
            'signature',
            'verbal',
            'implied',
            'opt_out',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for compliance reporting.
    |
    */
    'reporting' => [
        'enabled' => env('COMPLIANCE_REPORTING_ENABLED', true),
        'schedule' => env('COMPLIANCE_REPORTING_SCHEDULE', 'monthly'),
        'recipients' => env('COMPLIANCE_REPORT_RECIPIENTS', ''),
        'include_sensitive_data' => env('COMPLIANCE_REPORT_INCLUDE_SENSITIVE', false),
        'export_formats' => ['json', 'csv', 'pdf'],
        'max_report_size' => env('COMPLIANCE_MAX_REPORT_SIZE', 50 * 1024 * 1024), // 50MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Portability Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for data export and portability.
    |
    */
    'portability' => [
        'export_formats' => ['json', 'csv'],
        'include_metadata' => env('DATA_EXPORT_INCLUDE_METADATA', true),
        'compress_exports' => env('DATA_EXPORT_COMPRESS', true),
        'encryption_enabled' => env('DATA_EXPORT_ENCRYPTION', false),
        'max_concurrent_exports' => env('DATA_EXPORT_MAX_CONCURRENT', 5),
        'export_storage_disk' => env('DATA_EXPORT_STORAGE_DISK', 'local'),
    ],
];
