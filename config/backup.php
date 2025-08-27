<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Backup Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for database backups,
    | including full backups, incremental backups, and point-in-time recovery.
    |
    */

    'enabled' => env('BACKUP_ENABLED', true),

    'storage' => [
        'disk' => env('BACKUP_DISK', 'local'),
        'path' => env('BACKUP_PATH', 'backups/database'),
    ],

    'compression' => [
        'enabled' => env('BACKUP_COMPRESSION_ENABLED', true),
        'level' => env('BACKUP_COMPRESSION_LEVEL', 6), // 1-9, higher = better compression
        'format' => env('BACKUP_COMPRESSION_FORMAT', 'gzip'), // gzip, bzip2
    ],

    'retention' => [
        'daily' => env('BACKUP_RETENTION_DAILY', 7),
        'weekly' => env('BACKUP_RETENTION_WEEKLY', 4),
        'monthly' => env('BACKUP_RETENTION_MONTHLY', 12),
        'yearly' => env('BACKUP_RETENTION_YEARLY', 5),
    ],

    'schedule' => [
        'full_backup' => env('BACKUP_FULL_SCHEDULE', 'daily'),
        'incremental_backup' => env('BACKUP_INCREMENTAL_SCHEDULE', 'hourly'),
        'cleanup' => env('BACKUP_CLEANUP_SCHEDULE', 'daily'),
    ],

    'mysqldump' => [
        'path' => env('MYSQLDUMP_PATH', 'mysqldump'),
        'options' => [
            '--single-transaction',
            '--routines',
            '--triggers',
            '--lock-tables=false',
            '--add-drop-table',
            '--create-options',
            '--extended-insert',
            '--set-gtid-purged=OFF',
        ],
        'timeout' => env('BACKUP_TIMEOUT', 3600), // seconds
    ],

    'mysql' => [
        'path' => env('MYSQL_PATH', 'mysql'),
        'restore_timeout' => env('RESTORE_TIMEOUT', 7200), // seconds
    ],

    'verification' => [
        'enabled' => env('BACKUP_VERIFICATION_ENABLED', true),
        'test_restore' => env('BACKUP_TEST_RESTORE_ENABLED', false),
        'checksum' => env('BACKUP_CHECKSUM_ENABLED', true),
    ],

    'encryption' => [
        'enabled' => env('BACKUP_ENCRYPTION_ENABLED', false),
        'key' => env('BACKUP_ENCRYPTION_KEY'),
        'cipher' => env('BACKUP_ENCRYPTION_CIPHER', 'AES-256-CBC'),
    ],

    'notifications' => [
        'enabled' => env('BACKUP_NOTIFICATIONS_ENABLED', true),
        'channels' => ['email', 'log'],

        'email' => [
            'enabled' => env('BACKUP_EMAIL_NOTIFICATIONS_ENABLED', true),
            'recipients' => array_filter(explode(',', env('BACKUP_NOTIFICATION_EMAILS', '')) ?: ''),
            'on_success' => env('BACKUP_EMAIL_ON_SUCCESS', false),
            'on_failure' => env('BACKUP_EMAIL_ON_FAILURE', true),
        ],

        'slack' => [
            'enabled' => env('BACKUP_SLACK_NOTIFICATIONS_ENABLED', false),
            'webhook_url' => env('BACKUP_SLACK_WEBHOOK_URL'),
        ],
    ],

    'monitoring' => [
        'enabled' => env('BACKUP_MONITORING_ENABLED', true),
        'max_backup_age_hours' => env('BACKUP_MAX_AGE_HOURS', 25), // Alert if no backup in X hours
        'min_backup_size_mb' => env('BACKUP_MIN_SIZE_MB', 1), // Alert if backup is too small
        'max_backup_duration_minutes' => env('BACKUP_MAX_DURATION_MINUTES', 60),
    ],

    'point_in_time_recovery' => [
        'enabled' => env('PITR_ENABLED', true),
        'binary_log_enabled' => env('PITR_BINLOG_ENABLED', false),
        'binary_log_path' => env('PITR_BINLOG_PATH', '/var/log/mysql'),
        'retention_days' => env('PITR_RETENTION_DAYS', 7),
    ],

    'cloud_storage' => [
        's3' => [
            'enabled' => env('BACKUP_S3_ENABLED', false),
            'bucket' => env('BACKUP_S3_BUCKET'),
            'region' => env('BACKUP_S3_REGION', 'us-east-1'),
            'path' => env('BACKUP_S3_PATH', 'database-backups'),
            'storage_class' => env('BACKUP_S3_STORAGE_CLASS', 'STANDARD_IA'),
        ],

        'google_cloud' => [
            'enabled' => env('BACKUP_GCS_ENABLED', false),
            'bucket' => env('BACKUP_GCS_BUCKET'),
            'path' => env('BACKUP_GCS_PATH', 'database-backups'),
        ],

        'azure' => [
            'enabled' => env('BACKUP_AZURE_ENABLED', false),
            'container' => env('BACKUP_AZURE_CONTAINER'),
            'path' => env('BACKUP_AZURE_PATH', 'database-backups'),
        ],
    ],

    'parallel_processing' => [
        'enabled' => env('BACKUP_PARALLEL_ENABLED', false),
        'max_processes' => env('BACKUP_MAX_PROCESSES', 4),
        'chunk_size' => env('BACKUP_CHUNK_SIZE', 1000000), // rows
    ],

    'exclude_tables' => array_filter(explode(',', env('BACKUP_EXCLUDE_TABLES', '')) ?: ''),

    'include_only_tables' => array_filter(explode(',', env('BACKUP_INCLUDE_ONLY_TABLES', '')) ?: ''),

    'health_checks' => [
        'enabled' => env('BACKUP_HEALTH_CHECKS_ENABLED', true),
        'ping_url' => env('BACKUP_HEALTH_CHECK_URL'),
        'timeout' => env('BACKUP_HEALTH_CHECK_TIMEOUT', 30),
    ],
];
