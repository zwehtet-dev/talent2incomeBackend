<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Queue Management Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for advanced queue management features
    | including monitoring, scaling, health checks, and job scheduling.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Queue Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for queue monitoring and health checks.
    |
    */

    'monitoring' => [
        'enabled' => env('QUEUE_MONITORING_ENABLED', true),
        'health_check_interval' => env('QUEUE_HEALTH_CHECK_INTERVAL', 300), // 5 minutes
        'metrics_retention_days' => env('QUEUE_METRICS_RETENTION_DAYS', 30),

        'thresholds' => [
            'pending_jobs' => [
                'warning' => env('QUEUE_PENDING_WARNING', 1000),
                'critical' => env('QUEUE_PENDING_CRITICAL', 5000),
            ],
            'failed_jobs' => [
                'warning' => env('QUEUE_FAILED_WARNING', 50),
                'critical' => env('QUEUE_FAILED_CRITICAL', 200),
            ],
            'processing_time' => [
                'warning' => env('QUEUE_PROCESSING_WARNING', 300), // 5 minutes
                'critical' => env('QUEUE_PROCESSING_CRITICAL', 900), // 15 minutes
            ],
            'memory_usage' => [
                'warning' => env('QUEUE_MEMORY_WARNING', 80), // 80%
                'critical' => env('QUEUE_MEMORY_CRITICAL', 95), // 95%
            ],
            'queue_stall' => [
                'minutes' => env('QUEUE_STALL_MINUTES', 60),
            ],
        ],

        'alerts' => [
            'enabled' => env('QUEUE_ALERTS_ENABLED', true),
            'channels' => ['email', 'log'], // Available: email, slack, log, database
            'cooldown_minutes' => env('QUEUE_ALERT_COOLDOWN', 30),
            'admin_emails' => explode(',', env('QUEUE_ADMIN_EMAILS', '')) ?: 'S',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Recovery
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic queue recovery and healing.
    |
    */

    'auto_recovery' => [
        'enabled' => env('QUEUE_AUTO_RECOVERY_ENABLED', true),
        'max_retries' => env('QUEUE_AUTO_RECOVERY_RETRIES', 3),
        'retry_delay_minutes' => env('QUEUE_RETRY_DELAY', 5),
        'restart_threshold' => env('QUEUE_RESTART_THRESHOLD', 10), // failed jobs
        'clear_failed_after_days' => env('QUEUE_CLEAR_FAILED_DAYS', 7),

        'actions' => [
            'retry_failed_jobs' => env('QUEUE_AUTO_RETRY_FAILED', true),
            'restart_stalled_workers' => env('QUEUE_AUTO_RESTART_WORKERS', true),
            'clear_old_failed_jobs' => env('QUEUE_AUTO_CLEAR_OLD_FAILED', true),
            'scale_workers' => env('QUEUE_AUTO_SCALE_WORKERS', false), // Requires process manager
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Worker Scaling
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic worker scaling based on queue load.
    |
    */

    'scaling' => [
        'enabled' => env('QUEUE_SCALING_ENABLED', false), // Requires process manager
        'strategy' => env('QUEUE_SCALING_STRATEGY', 'load_based'), // load_based, time_based, hybrid

        'load_based' => [
            'min_workers' => env('QUEUE_MIN_WORKERS', 2),
            'max_workers' => env('QUEUE_MAX_WORKERS', 20),
            'scale_up_threshold' => env('QUEUE_SCALE_UP_THRESHOLD', 10), // jobs per worker
            'scale_down_threshold' => env('QUEUE_SCALE_DOWN_THRESHOLD', 2), // jobs per worker
            'scale_up_cooldown' => env('QUEUE_SCALE_UP_COOLDOWN', 300), // seconds
            'scale_down_cooldown' => env('QUEUE_SCALE_DOWN_COOLDOWN', 600), // seconds
        ],

        'time_based' => [
            'peak_hours' => [
                'start' => env('QUEUE_PEAK_START', '09:00'),
                'end' => env('QUEUE_PEAK_END', '17:00'),
                'workers' => env('QUEUE_PEAK_WORKERS', 10),
            ],
            'off_peak_hours' => [
                'workers' => env('QUEUE_OFF_PEAK_WORKERS', 3),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Priorities
    |--------------------------------------------------------------------------
    |
    | Define queue priorities and their characteristics.
    |
    */

    'priorities' => [
        'critical' => [
            'weight' => 10,
            'max_workers' => env('QUEUE_CRITICAL_WORKERS', 3),
            'timeout' => env('QUEUE_CRITICAL_TIMEOUT', 30),
            'memory' => env('QUEUE_CRITICAL_MEMORY', 256),
            'retry_after' => env('QUEUE_CRITICAL_RETRY_AFTER', 30),
            'job_types' => [
                'payment_processing',
                'security_alerts',
                'authentication_failures',
                'system_critical_errors',
            ],
        ],

        'high' => [
            'weight' => 5,
            'max_workers' => env('QUEUE_HIGH_WORKERS', 5),
            'timeout' => env('QUEUE_HIGH_TIMEOUT', 60),
            'memory' => env('QUEUE_HIGH_MEMORY', 256),
            'retry_after' => env('QUEUE_HIGH_RETRY_AFTER', 60),
            'job_types' => [
                'user_notifications',
                'real_time_messaging',
                'user_actions',
                'api_responses',
            ],
        ],

        'normal' => [
            'weight' => 3,
            'max_workers' => env('QUEUE_NORMAL_WORKERS', 3),
            'timeout' => env('QUEUE_NORMAL_TIMEOUT', 90),
            'memory' => env('QUEUE_NORMAL_MEMORY', 256),
            'retry_after' => env('QUEUE_NORMAL_RETRY_AFTER', 90),
            'job_types' => [
                'email_notifications',
                'search_indexing',
                'cache_updates',
                'data_processing',
            ],
        ],

        'low' => [
            'weight' => 2,
            'max_workers' => env('QUEUE_LOW_WORKERS', 2),
            'timeout' => env('QUEUE_LOW_TIMEOUT', 120),
            'memory' => env('QUEUE_LOW_MEMORY', 512),
            'retry_after' => env('QUEUE_LOW_RETRY_AFTER', 120),
            'job_types' => [
                'data_cleanup',
                'analytics_processing',
                'report_generation',
                'maintenance_tasks',
            ],
        ],

        'bulk' => [
            'weight' => 1,
            'max_workers' => env('QUEUE_BULK_WORKERS', 1),
            'timeout' => env('QUEUE_BULK_TIMEOUT', 300),
            'memory' => env('QUEUE_BULK_MEMORY', 1024),
            'retry_after' => env('QUEUE_BULK_RETRY_AFTER', 300),
            'job_types' => [
                'data_migrations',
                'bulk_operations',
                'large_exports',
                'system_maintenance',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Scheduling
    |--------------------------------------------------------------------------
    |
    | Configuration for scheduled recurring jobs.
    |
    */

    'scheduling' => [
        'enabled' => env('QUEUE_SCHEDULING_ENABLED', true),
        'timezone' => env('QUEUE_SCHEDULING_TIMEZONE', 'UTC'),

        'jobs' => [
            'queue_health_check' => [
                'enabled' => env('SCHEDULE_HEALTH_CHECK', true),
                'interval' => '*/5', // Every 5 minutes
                'queue' => 'high',
                'description' => 'Monitor queue health and performance',
            ],

            'daily_cleanup' => [
                'enabled' => env('SCHEDULE_DAILY_CLEANUP', true),
                'time' => '02:00',
                'queue' => 'low',
                'description' => 'Daily data cleanup and maintenance',
            ],

            'weekly_cleanup' => [
                'enabled' => env('SCHEDULE_WEEKLY_CLEANUP', true),
                'day' => 'sunday',
                'time' => '03:00',
                'queue' => 'bulk',
                'description' => 'Weekly comprehensive cleanup',
            ],

            'search_maintenance' => [
                'enabled' => env('SCHEDULE_SEARCH_MAINTENANCE', true),
                'time' => '01:00',
                'queue' => 'normal',
                'description' => 'Daily search index maintenance',
            ],

            'search_rebuild' => [
                'enabled' => env('SCHEDULE_SEARCH_REBUILD', true),
                'day' => 1, // First day of month
                'time' => '04:00',
                'queue' => 'bulk',
                'description' => 'Monthly search index rebuild',
            ],

            'rating_cache_update' => [
                'enabled' => env('SCHEDULE_RATING_CACHE', true),
                'time' => '00:30',
                'queue' => 'normal',
                'description' => 'Daily rating cache update',
            ],

            'daily_analytics' => [
                'enabled' => env('SCHEDULE_DAILY_ANALYTICS', true),
                'time' => '05:00',
                'queue' => 'low',
                'description' => 'Daily analytics processing',
            ],

            'saved_search_notifications' => [
                'enabled' => env('SCHEDULE_SAVED_SEARCH_NOTIFICATIONS', true),
                'interval' => '0 */6', // Every 6 hours
                'queue' => 'normal',
                'description' => 'Process saved search notifications',
            ],

            'worker_scaling_check' => [
                'enabled' => env('SCHEDULE_WORKER_SCALING', false),
                'interval' => '*/10', // Every 10 minutes
                'queue' => 'high',
                'description' => 'Check and adjust worker scaling',
            ],

            'failed_job_cleanup' => [
                'enabled' => env('SCHEDULE_FAILED_JOB_CLEANUP', true),
                'time' => '06:00',
                'queue' => 'low',
                'description' => 'Clean up old failed jobs',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    |
    | Configuration for queue performance optimization.
    |
    */

    'performance' => [
        'batch_processing' => [
            'enabled' => env('QUEUE_BATCH_PROCESSING', true),
            'default_batch_size' => env('QUEUE_DEFAULT_BATCH_SIZE', 100),
            'max_batch_size' => env('QUEUE_MAX_BATCH_SIZE', 1000),
        ],

        'connection_pooling' => [
            'enabled' => env('QUEUE_CONNECTION_POOLING', true),
            'pool_size' => env('QUEUE_CONNECTION_POOL_SIZE', 10),
        ],

        'job_compression' => [
            'enabled' => env('QUEUE_JOB_COMPRESSION', false),
            'threshold_bytes' => env('QUEUE_COMPRESSION_THRESHOLD', 1024),
        ],

        'prefetch' => [
            'enabled' => env('QUEUE_PREFETCH_ENABLED', true),
            'count' => env('QUEUE_PREFETCH_COUNT', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging and Debugging
    |--------------------------------------------------------------------------
    |
    | Configuration for queue logging and debugging.
    |
    */

    'logging' => [
        'enabled' => env('QUEUE_LOGGING_ENABLED', true),
        'level' => env('QUEUE_LOG_LEVEL', 'info'), // debug, info, warning, error
        'channels' => ['queue'], // Log channels to use

        'log_job_start' => env('QUEUE_LOG_JOB_START', false),
        'log_job_completion' => env('QUEUE_LOG_JOB_COMPLETION', true),
        'log_job_failure' => env('QUEUE_LOG_JOB_FAILURE', true),
        'log_slow_jobs' => env('QUEUE_LOG_SLOW_JOBS', true),
        'slow_job_threshold' => env('QUEUE_SLOW_JOB_THRESHOLD', 60), // seconds

        'metrics' => [
            'enabled' => env('QUEUE_METRICS_ENABLED', true),
            'store_job_timing' => env('QUEUE_STORE_JOB_TIMING', true),
            'store_memory_usage' => env('QUEUE_STORE_MEMORY_USAGE', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Security configuration for queue operations.
    |
    */

    'security' => [
        'encrypt_payloads' => env('QUEUE_ENCRYPT_PAYLOADS', false),
        'sign_payloads' => env('QUEUE_SIGN_PAYLOADS', true),
        'max_payload_size' => env('QUEUE_MAX_PAYLOAD_SIZE', 1048576), // 1MB

        'rate_limiting' => [
            'enabled' => env('QUEUE_RATE_LIMITING', false),
            'max_jobs_per_minute' => env('QUEUE_MAX_JOBS_PER_MINUTE', 1000),
        ],

        'job_validation' => [
            'enabled' => env('QUEUE_JOB_VALIDATION', true),
            'allowed_classes' => [], // Empty means all allowed
            'blocked_classes' => [], // Classes to block
        ],
    ],

];
