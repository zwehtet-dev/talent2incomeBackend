<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Queue Health Alert</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: {{ $severity === 'critical' ? '#dc3545' : ($severity === 'warning' ? '#ffc107' : '#17a2b8') }};
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .content {
            background: #f8f9fa;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-top: none;
        }
        .alert-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .alert-critical {
            border-left: 4px solid #dc3545;
        }
        .alert-warning {
            border-left: 4px solid #ffc107;
        }
        .queue-name {
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 8px;
        }
        .issue-list {
            margin: 10px 0;
            padding-left: 20px;
        }
        .issue-list li {
            margin-bottom: 5px;
        }
        .stats {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 0.9em;
            color: #666;
        }
        .footer {
            background: #343a40;
            color: white;
            padding: 20px;
            border-radius: 0 0 8px 8px;
            text-align: center;
        }
        .button {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 10px 0;
        }
        .summary {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .icon {
            font-size: 1.2em;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            @if($severity === 'critical')
                🚨 Critical Queue Health Alert
            @elseif($severity === 'warning')
                ⚠️ Queue Health Warning
            @else
                📊 Queue Health Notification
            @endif
        </h1>
        <p>{{ config('app.name') }} - Queue Monitoring System</p>
    </div>

    <div class="content">
        <div class="summary">
            <h2>Alert Summary</h2>
            <p><strong>Time:</strong> {{ $timestamp }}</p>
            <p><strong>Total Issues:</strong> {{ $totalIssues }}</p>
            <p><strong>Severity:</strong> 
                @if($severity === 'critical')
                    <span style="color: #dc3545; font-weight: bold;">CRITICAL</span>
                @elseif($severity === 'warning')
                    <span style="color: #ffc107; font-weight: bold;">WARNING</span>
                @else
                    <span style="color: #17a2b8; font-weight: bold;">INFO</span>
                @endif
            </p>
        </div>

        <h2>Queue Issues Detected</h2>

        @foreach($alerts as $alert)
            <div class="alert-item {{ $alert['status'] === 'critical' ? 'alert-critical' : 'alert-warning' }}">
                <div class="queue-name">
                    @if($alert['status'] === 'critical')
                        <span class="icon">❌</span>
                    @else
                        <span class="icon">⚠️</span>
                    @endif
                    Queue: {{ $alert['queue'] }}
                </div>

                @if(!empty($alert['issues']))
                    <ul class="issue-list">
                        @foreach($alert['issues'] as $issue)
                            <li>{{ ucfirst(str_replace('_', ' ', $issue)) }}</li>
                        @endforeach
                    </ul>
                @endif

                <div class="stats">
                    @if(isset($alert['pending']))
                        <span>📋 Pending: {{ number_format($alert['pending']) }}</span>
                    @endif
                    @if(isset($alert['failed']))
                        <span>❌ Failed: {{ number_format($alert['failed']) }}</span>
                    @endif
                    @if(isset($alert['avg_processing_time']))
                        <span>⏱️ Avg Time: {{ number_format($alert['avg_processing_time'], 1) }}s</span>
                    @endif
                    @if(isset($alert['active_workers']))
                        <span>👥 Workers: {{ $alert['active_workers'] }}/{{ $alert['recommended_workers'] ?? 'N/A' }}</span>
                    @endif
                </div>

                @if(isset($alert['error']))
                    <div style="margin-top: 10px; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">
                        <strong>Error:</strong> {{ $alert['error'] }}
                    </div>
                @endif
            </div>
        @endforeach

        <h2>Recommended Actions</h2>
        <ul>
            @if(collect($alerts)->contains(function($alert) { return in_array('high_failed_jobs', $alert['issues'] ?? []) || in_array('critical_failed_jobs', $alert['issues'] ?? []); }))
                <li>Review and retry failed jobs using: <code>php artisan jobs:manage retry</code></li>
            @endif
            @if(collect($alerts)->contains(function($alert) { return in_array('insufficient_workers', $alert['issues'] ?? []); }))
                <li>Scale up worker processes using: <code>php artisan queue:workers start --workers=3</code></li>
            @endif
            @if(collect($alerts)->contains(function($alert) { return in_array('stalled_queue', $alert['issues'] ?? []); }))
                <li>Restart queue workers using: <code>php artisan queue:workers restart</code></li>
            @endif
            @if(collect($alerts)->contains(function($alert) { return $alert['queue'] === 'redis'; }))
                <li>Check Redis server status and connection configuration</li>
            @endif
            <li>Monitor queue dashboard for real-time updates</li>
            <li>Check application logs for detailed error information</li>
        </ul>
    </div>

    <div class="footer">
        <p>This is an automated alert from the {{ config('app.name') }} queue monitoring system.</p>
        <a href="{{ $dashboardUrl }}" class="button">View Queue Dashboard</a>
        <p style="font-size: 0.9em; margin-top: 15px;">
            To modify alert settings or disable notifications, please contact your system administrator.
        </p>
    </div>
</body>
</html>