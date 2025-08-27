<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Background Job System Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the comprehensive
    | background job system including queues, retry policies, and
    | monitoring settings.
    |
    */

    'queues' => [
        'high' => [
            'connection' => 'redis-high',
            'timeout' => 30,
            'retry_after' => 60,
            'max_tries' => 5,
            'backoff' => [10, 30, 60, 120, 300],
            'priority' => 10,
            'description' => 'Critical operations requiring immediate processing',
        ],
        'default' => [
            'connection' => 'redis',
            'timeout' => 60,
            'retry_after' => 90,
            'max_tries' => 3,
            'backoff' => [30, 60, 120],
            'priority' => 5,
            'description' => 'Standard operations',
        ],
        'emails' => [
            'connection' => 'redis',
            'timeout' => 60,
            'retry_after' => 90,
            'max_tries' => 3,
            'backoff' => [30, 60, 120],
            'priority' => 4,
            'description' => 'Email notifications and communications',
        ],
        'payments' => [
            'connection' => 'redis',
            'timeout' => 120,
            'retry_after' => 180,
            'max_tries' => 5,
            'backoff' => [30, 60, 120, 300, 600],
            'priority' => 8,
            'description' => 'Payment processing and financial operations',
        ],
        'search' => [
            'connection' => 'redis',
            'timeout' => 600, // 10 minutes
            'retry_after' => 720,
            'max_tries' => 3,
            'backoff' => [60, 120, 300],
            'priority' => 3,
            'description' => 'Search index updates and maintenance',
        ],
        'analytics' => [
            'connection' => 'redis',
            'timeout' => 300, // 5 minutes
            'retry_after' => 360,
            'max_tries' => 3,
            'backoff' => [30, 60, 120],
            'priority' => 2,
            'description' => 'Analytics processing and reporting',
        ],
        'cleanup' => [
            'connection' => 'redis-low',
            'timeout' => 1800, // 30 minutes
            'retry_after' => 2100,
            'max_tries' => 3,
            'backoff' => [300, 600, 1200],
            'priority' => 1,
            'description' => 'Data cleanup and maintenance tasks',
        ],
        'reports' => [
            'connection' => 'redis-low',
            'timeout' => 1800, // 30 minutes
            'retry_after' => 2100,
            'max_tries' => 3,
            'backoff' => [300, 600, 1200],
            'priority' => 1,
            'description' => 'Report generation and processing',
        ],
        'low' => [
            'connection' => 'redis-low',
            'timeout' => 3600, // 1 hour
            'retry_after' => 4200,
            'max_tries' => 2,
            'backoff' => [600, 1800],
            'priority' => 0,
            'description' => 'Low priority background tasks',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Templates
    |--------------------------------------------------------------------------
    |
    | Mapping of email template names to their corresponding Mailable classes.
    |
    */
    'email_templates' => [
        'welcome' => \App\Mail\WelcomeEmail::class,
        'job_application' => \App\Mail\JobApplicationEmail::class,
        'job_completed' => \App\Mail\JobCompletedEmail::class,
        'payment_received' => \App\Mail\PaymentReceivedEmail::class,
        'payment_released' => \App\Mail\PaymentReleasedEmail::class,
        'review_received' => \App\Mail\ReviewReceivedEmail::class,
        'message_received' => \App\Mail\MessageReceivedEmail::class,
        'password_reset' => \App\Mail\PasswordResetEmail::class,
        'email_verification' => \App\Mail\EmailVerificationEmail::class,
        'account_suspended' => \App\Mail\AccountSuspendedEmail::class,
        'dispute_created' => \App\Mail\DisputeCreatedEmail::class,
        'analytics_report' => \App\Mail\AnalyticsReport::class,
        'saved_search_notification' => \App\Mail\SavedSearchNotification::class,
        'queue_health_alert' => \App\Mail\QueueHealthAlert::class,
        'report_generation_failed' => \App\Mail\ReportGenerationFailed::class,
        'payment_failed' => \App\Mail\PaymentFailedEmail::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for automated data cleanup and archival processes.
    |
    */
    'cleanup' => [
        'expired_jobs' => [
            'days_old' => 90,
            'permanent_delete_days' => 365,
        ],
        'old_messages' => [
            'days_old' => 180,
        ],
        'inactive_users' => [
            'days_inactive' => 730, // 2 years
        ],
        'completed_payments' => [
            'days_old' => 365,
        ],
        'old_reviews' => [
            'days_old' => 1095, // 3 years
        ],
        'temp_files' => [
            'hours_old' => 24,
        ],
        'logs' => [
            'days_old' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Index Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for search index management and batch processing.
    |
    */
    'search_index' => [
        'batch_size' => 100,
        'models' => [
            'jobs' => \App\Models\Job::class,
            'skills' => \App\Models\Skill::class,
            'users' => \App\Models\User::class,
        ],
        'rebuild_schedule' => 'monthly', // daily, weekly, monthly
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for payment job processing and retry logic.
    |
    */
    'payments' => [
        'gateway_timeout' => 30, // seconds
        'failure_rate_simulation' => 5, // percentage for testing
        'retry_delays' => [30, 60, 120, 300, 600], // seconds
        'max_retries' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Report Generation Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for report generation jobs and progress tracking.
    |
    */
    'reports' => [
        'storage_path' => 'reports',
        'max_file_size' => 50 * 1024 * 1024, // 50MB
        'progress_cache_ttl' => 300, // 5 minutes
        'supported_formats' => ['json', 'csv', 'pdf'],
        'batch_size' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Alerting
    |--------------------------------------------------------------------------
    |
    | Thresholds and settings for job queue monitoring and health checks.
    |
    */
    'monitoring' => [
        'enabled' => env('QUEUE_MONITORING_ENABLED', true),
        'health_check_interval' => 300, // 5 minutes
        'auto_scaling_enabled' => env('QUEUE_AUTO_SCALING_ENABLED', false),
        'thresholds' => [
            'pending_jobs_warning' => 1000,
            'pending_jobs_critical' => 5000,
            'failed_jobs_warning' => 50,
            'failed_jobs_critical' => 200,
            'processing_time_warning' => 300, // 5 minutes
            'processing_time_critical' => 900, // 15 minutes
            'memory_usage_warning' => 80, // 80%
            'memory_usage_critical' => 95, // 95%
            'stalled_queue_minutes' => 60, // 1 hour
        ],
        'alert_recipients' => [
            // Will be populated from admin users
        ],
        'redis_monitoring' => [
            'enabled' => true,
            'memory_threshold' => 90, // 90%
            'connection_threshold' => 1000,
        ],
        'worker_monitoring' => [
            'enabled' => true,
            'min_workers' => 2,
            'max_workers' => 10,
            'scale_up_threshold' => 100, // pending jobs per worker
            'scale_down_threshold' => 10, // pending jobs per worker
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recurring Job Schedule
    |--------------------------------------------------------------------------
    |
    | Configuration for automatically scheduled recurring jobs.
    |
    */
    'recurring_jobs' => [
        'daily_cleanup' => [
            'time' => '02:00',
            'timezone' => 'UTC',
            'enabled' => true,
        ],
        'weekly_full_cleanup' => [
            'day' => 'sunday',
            'time' => '03:00',
            'timezone' => 'UTC',
            'enabled' => true,
        ],
        'daily_search_maintenance' => [
            'time' => '01:00',
            'timezone' => 'UTC',
            'enabled' => true,
        ],
        'monthly_search_rebuild' => [
            'day' => 1,
            'time' => '04:00',
            'timezone' => 'UTC',
            'enabled' => true,
        ],
        'health_monitoring' => [
            'interval' => '*/5', // every 5 minutes
            'enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch Processing
    |--------------------------------------------------------------------------
    |
    | Settings for batch job processing to prevent system overload.
    |
    */
    'batch_processing' => [
        'email_batch_size' => 50,
        'email_batch_delay' => 60, // seconds between batches
        'search_batch_size' => 100,
        'cleanup_batch_size' => 500,
        'report_batch_size' => 1000,
    ],
];
