<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue supports a variety of backends via a single, unified
    | API, giving you convenient access to each backend using identical
    | syntax for each. The default queue connection is defined below.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every queue backend
    | used by your application. An example configuration is provided for
    | each backend supported by Laravel. You're also free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 90),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

        // Critical priority queue for payments and security operations
        'redis-critical' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => 'critical',
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 30),
            'block_for' => null,
            'after_commit' => false,
        ],

        // High priority queue for user-facing operations
        'redis-high' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => 'high',
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 60),
            'block_for' => null,
            'after_commit' => false,
        ],

        // Normal priority queue for standard operations
        'redis-normal' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => 'normal',
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

        // Low priority queue for background tasks
        'redis-low' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => 'low',
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 120),
            'block_for' => null,
            'after_commit' => false,
        ],

        // Bulk operations queue for large data processing
        'redis-bulk' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => 'bulk',
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 300),
            'block_for' => null,
            'after_commit' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control how and where failed jobs are stored. Laravel ships with
    | support for storing failed jobs in a simple file or in a database.
    |
    | Supported drivers: "database-uuids", "dynamodb", "file", "null"
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for queue monitoring, health checks, and worker management.
    |
    */

    'monitoring' => [
        'enabled' => env('QUEUE_MONITORING_ENABLED', true),
        'health_check_interval' => env('QUEUE_HEALTH_CHECK_INTERVAL', 60), // seconds
        'max_failed_jobs' => env('QUEUE_MAX_FAILED_JOBS', 100),
        'max_queue_size' => env('QUEUE_MAX_SIZE', 1000),
        'worker_timeout' => env('QUEUE_WORKER_TIMEOUT', 300), // seconds
        'memory_limit' => env('QUEUE_WORKER_MEMORY', 512), // MB
        'alert_thresholds' => [
            'failed_jobs' => env('QUEUE_ALERT_FAILED_JOBS', 50),
            'queue_size' => env('QUEUE_ALERT_QUEUE_SIZE', 500),
            'processing_time' => env('QUEUE_ALERT_PROCESSING_TIME', 300), // seconds
        ],
        'auto_recovery' => [
            'enabled' => env('QUEUE_AUTO_RECOVERY_ENABLED', true),
            'max_retries' => env('QUEUE_AUTO_RECOVERY_RETRIES', 3),
            'restart_threshold' => env('QUEUE_RESTART_THRESHOLD', 10), // failed jobs
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Priority Configuration
    |--------------------------------------------------------------------------
    |
    | Define queue priorities and their corresponding worker allocation.
    |
    */

    'priorities' => [
        'critical' => [
            'weight' => 10,
            'max_workers' => env('QUEUE_CRITICAL_WORKERS', 3),
            'job_types' => ['payment', 'security', 'authentication'],
        ],
        'high' => [
            'weight' => 5,
            'max_workers' => env('QUEUE_HIGH_WORKERS', 5),
            'job_types' => ['notification', 'messaging', 'user_action'],
        ],
        'normal' => [
            'weight' => 3,
            'max_workers' => env('QUEUE_NORMAL_WORKERS', 3),
            'job_types' => ['email', 'search_index', 'cache_update'],
        ],
        'low' => [
            'weight' => 2,
            'max_workers' => env('QUEUE_LOW_WORKERS', 2),
            'job_types' => ['cleanup', 'analytics', 'reporting'],
        ],
        'bulk' => [
            'weight' => 1,
            'max_workers' => env('QUEUE_BULK_WORKERS', 1),
            'job_types' => ['data_migration', 'bulk_operations'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Worker Scaling Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic worker scaling based on queue load.
    |
    */

    'scaling' => [
        'enabled' => env('QUEUE_SCALING_ENABLED', true),
        'min_workers' => env('QUEUE_MIN_WORKERS', 2),
        'max_workers' => env('QUEUE_MAX_WORKERS', 20),
        'scale_up_threshold' => env('QUEUE_SCALE_UP_THRESHOLD', 10), // jobs per worker
        'scale_down_threshold' => env('QUEUE_SCALE_DOWN_THRESHOLD', 2), // jobs per worker
        'scale_up_cooldown' => env('QUEUE_SCALE_UP_COOLDOWN', 300), // seconds
        'scale_down_cooldown' => env('QUEUE_SCALE_DOWN_COOLDOWN', 600), // seconds
    ],

];
