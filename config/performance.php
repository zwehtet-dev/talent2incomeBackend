<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for performance monitoring,
    | including query logging, slow query detection, memory usage tracking,
    | and response time monitoring.
    |
    */

    'enabled' => env('PERFORMANCE_MONITORING_ENABLED', true),

    'query_monitoring' => [
        'enabled' => env('QUERY_MONITORING_ENABLED', true),
        'slow_query_threshold' => env('SLOW_QUERY_THRESHOLD', 1000), // milliseconds
        'log_all_queries' => env('LOG_ALL_QUERIES', false),
        'log_slow_queries' => env('LOG_SLOW_QUERIES', true),
    ],

    'memory_monitoring' => [
        'enabled' => env('MEMORY_MONITORING_ENABLED', true),
        'threshold_mb' => env('MEMORY_THRESHOLD_MB', 128),
        'log_peak_usage' => env('LOG_PEAK_MEMORY_USAGE', true),
    ],

    'response_time_monitoring' => [
        'enabled' => env('RESPONSE_TIME_MONITORING_ENABLED', true),
        'slow_response_threshold' => env('SLOW_RESPONSE_THRESHOLD', 2000), // milliseconds
        'log_slow_responses' => env('LOG_SLOW_RESPONSES', true),
    ],

    'cache_monitoring' => [
        'enabled' => env('CACHE_MONITORING_ENABLED', true),
        'log_cache_hits' => env('LOG_CACHE_HITS', false),
        'log_cache_misses' => env('LOG_CACHE_MISSES', true),
    ],

    'queue_monitoring' => [
        'enabled' => env('QUEUE_MONITORING_ENABLED', true),
        'log_job_failures' => env('LOG_JOB_FAILURES', true),
        'log_job_timeouts' => env('LOG_JOB_TIMEOUTS', true),
        'failed_job_retention_days' => env('FAILED_JOB_RETENTION_DAYS', 30),
    ],

    'api_monitoring' => [
        'enabled' => env('API_MONITORING_ENABLED', true),
        'log_api_calls' => env('LOG_API_CALLS', true),
        'log_api_errors' => env('LOG_API_ERRORS', true),
        'track_user_agents' => env('TRACK_USER_AGENTS', true),
        'track_ip_addresses' => env('TRACK_IP_ADDRESSES', true),
    ],

    'alerts' => [
        'enabled' => env('PERFORMANCE_ALERTS_ENABLED', false),
        'slack_webhook' => env('PERFORMANCE_SLACK_WEBHOOK'),
        'email_recipients' => env('PERFORMANCE_ALERT_EMAILS', ''),
        'thresholds' => [
            'cpu_usage_percent' => 80,
            'memory_usage_percent' => 85,
            'disk_usage_percent' => 90,
            'response_time_ms' => 5000,
            'error_rate_percent' => 5,
        ],
    ],

    'reporting' => [
        'enabled' => env('PERFORMANCE_REPORTING_ENABLED', true),
        'daily_reports' => env('DAILY_PERFORMANCE_REPORTS', false),
        'weekly_reports' => env('WEEKLY_PERFORMANCE_REPORTS', true),
        'monthly_reports' => env('MONTHLY_PERFORMANCE_REPORTS', true),
        'report_recipients' => env('PERFORMANCE_REPORT_EMAILS', ''),
    ],
];
