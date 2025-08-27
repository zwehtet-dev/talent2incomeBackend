<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Performance Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for database performance
    | monitoring, optimization, and connection pooling.
    |
    */

    'monitoring' => [
        'enabled' => env('DB_MONITORING_ENABLED', true),

        'slow_query_thresholds' => [
            'select' => env('DB_SLOW_SELECT_THRESHOLD', 1000), // milliseconds
            'insert' => env('DB_SLOW_INSERT_THRESHOLD', 500),
            'update' => env('DB_SLOW_UPDATE_THRESHOLD', 500),
            'delete' => env('DB_SLOW_DELETE_THRESHOLD', 500),
        ],

        'alert_thresholds' => [
            'connection_usage_percent' => env('DB_ALERT_CONNECTION_USAGE', 80),
            'slow_query_percent' => env('DB_ALERT_SLOW_QUERY_PERCENT', 10),
            'avg_query_time' => env('DB_ALERT_AVG_QUERY_TIME', 500),
            'pool_utilization' => env('DB_ALERT_POOL_UTILIZATION', 90),
        ],

        'metrics_retention_days' => env('DB_METRICS_RETENTION_DAYS', 30),
        'slow_query_retention_days' => env('DB_SLOW_QUERY_RETENTION_DAYS', 7),
    ],

    'connection_pool' => [
        'enabled' => env('DB_POOL_ENABLED', true),

        'max_connections' => env('DB_POOL_MAX_CONNECTIONS', 20),
        'min_connections' => env('DB_POOL_MIN_CONNECTIONS', 5),
        'connection_timeout' => env('DB_POOL_CONNECTION_TIMEOUT', 30),

        'pools' => [
            'read' => [
                'connection' => env('DB_POOL_READ_CONNECTION', 'mysql'),
                'max_connections' => env('DB_POOL_READ_MAX', 15),
            ],
            'write' => [
                'connection' => env('DB_POOL_WRITE_CONNECTION', 'mysql'),
                'max_connections' => env('DB_POOL_WRITE_MAX', 10),
            ],
            'analytics' => [
                'connection' => env('DB_POOL_ANALYTICS_CONNECTION', 'mysql'),
                'max_connections' => env('DB_POOL_ANALYTICS_MAX', 5),
            ],
        ],

        'cleanup_interval' => env('DB_POOL_CLEANUP_INTERVAL', 300), // seconds
        'stale_connection_threshold' => env('DB_POOL_STALE_THRESHOLD', 1800), // seconds
    ],

    'optimization' => [
        'auto_optimize_tables' => env('DB_AUTO_OPTIMIZE_TABLES', false),
        'auto_analyze_tables' => env('DB_AUTO_ANALYZE_TABLES', true),

        'optimization_schedule' => [
            'analyze' => env('DB_ANALYZE_SCHEDULE', 'daily'),
            'optimize' => env('DB_OPTIMIZE_SCHEDULE', 'weekly'),
        ],

        'table_size_alert_threshold' => env('DB_TABLE_SIZE_ALERT_THRESHOLD', 1073741824), // 1GB
        'fragmentation_alert_threshold' => env('DB_FRAGMENTATION_ALERT_THRESHOLD', 30), // percent
    ],

    'logging' => [
        'slow_queries' => [
            'enabled' => env('DB_LOG_SLOW_QUERIES', true),
            'channel' => env('DB_SLOW_QUERY_LOG_CHANNEL', 'slow_queries'),
        ],

        'performance' => [
            'enabled' => env('DB_LOG_PERFORMANCE', true),
            'channel' => env('DB_PERFORMANCE_LOG_CHANNEL', 'performance'),
        ],

        'connection_pool' => [
            'enabled' => env('DB_LOG_POOL', false),
            'channel' => env('DB_POOL_LOG_CHANNEL', 'database'),
        ],
    ],

    'alerts' => [
        'enabled' => env('DB_ALERTS_ENABLED', true),
        'channels' => ['email', 'log'],

        'email' => [
            'enabled' => env('DB_EMAIL_ALERTS_ENABLED', true),
            'recipients' => array_filter(explode(',', env('DB_ALERT_EMAILS', '')) ?: ''),
        ],

        'slack' => [
            'enabled' => env('DB_SLACK_ALERTS_ENABLED', false),
            'webhook_url' => env('DB_SLACK_WEBHOOK_URL'),
        ],

        'rate_limiting' => [
            'critical_alert_interval' => env('DB_CRITICAL_ALERT_INTERVAL', 3600), // seconds
            'warning_alert_interval' => env('DB_WARNING_ALERT_INTERVAL', 3600),
        ],
    ],

    'query_analysis' => [
        'enabled' => env('DB_QUERY_ANALYSIS_ENABLED', true),
        'explain_slow_queries' => env('DB_EXPLAIN_SLOW_QUERIES', true),
        'suggest_optimizations' => env('DB_SUGGEST_OPTIMIZATIONS', true),

        'analysis_rules' => [
            'detect_n_plus_one' => true,
            'detect_missing_indexes' => true,
            'detect_select_star' => true,
            'detect_large_offset' => true,
        ],
    ],
];
