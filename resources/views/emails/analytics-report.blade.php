<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report->name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .summary {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .highlight {
            display: inline-block;
            background-color: #fff;
            padding: 10px 15px;
            margin: 5px;
            border-radius: 5px;
            border-left: 4px solid #2196f3;
        }
        .metric-value {
            font-size: 1.2em;
            font-weight: bold;
            color: #1976d2;
        }
        .growth-positive {
            color: #4caf50;
        }
        .growth-negative {
            color: #f44336;
        }
        .alert {
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
        }
        .alert-error {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
        }
        .alert-warning {
            background-color: #fff3e0;
            border-left: 4px solid #ff9800;
        }
        .alert-critical {
            background-color: #fce4ec;
            border-left: 4px solid #e91e63;
        }
        .data-section {
            margin: 20px 0;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 8px;
        }
        .data-section h3 {
            margin-top: 0;
            color: #1976d2;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $report->name }}</h1>
        <p><strong>Report Type:</strong> {{ ucfirst($report->type) }}</p>
        <p><strong>Period:</strong> {{ $summary['period']['start_date'] ?? 'N/A' }} to {{ $summary['period']['end_date'] ?? 'N/A' }}</p>
        <p><strong>Generated:</strong> {{ $report->generated_at->format('F j, Y \a\t g:i A') }}</p>
    </div>

    <div class="summary">
        <h2>Key Highlights</h2>
        @foreach($summary['key_highlights'] as $highlight)
            <div class="highlight">
                <div>{{ $highlight['metric'] }}</div>
                <div class="metric-value">{{ $highlight['value'] }}</div>
                @if($highlight['growth'] !== null)
                    <div class="{{ $highlight['growth'] >= 0 ? 'growth-positive' : 'growth-negative' }}">
                        {{ $highlight['growth'] >= 0 ? '+' : '' }}{{ number_format($highlight['growth'], 1) }}%
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if(!empty($summary['alerts']))
        <div class="data-section">
            <h3>System Alerts</h3>
            @foreach($summary['alerts'] as $alert)
                <div class="alert alert-{{ $alert['type'] }}">
                    <strong>{{ ucfirst($alert['type']) }}:</strong> {{ $alert['message'] }}
                </div>
            @endforeach
        </div>
    @endif

    @if(isset($data['revenue']))
        <div class="data-section">
            <h3>Revenue Analytics</h3>
            <table>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Total Revenue</td>
                    <td>${{ number_format($data['revenue']['total_revenue'], 2) }}</td>
                </tr>
                <tr>
                    <td>Platform Fees</td>
                    <td>${{ number_format($data['revenue']['platform_fees'], 2) }}</td>
                </tr>
                <tr>
                    <td>Net Revenue</td>
                    <td>${{ number_format($data['revenue']['net_revenue'], 2) }}</td>
                </tr>
                <tr>
                    <td>Total Transactions</td>
                    <td>{{ number_format($data['revenue']['total_transactions']) }}</td>
                </tr>
                <tr>
                    <td>Average Transaction Value</td>
                    <td>${{ number_format($data['revenue']['average_transaction_value'], 2) }}</td>
                </tr>
                @if(isset($data['revenue']['growth_rate']))
                    <tr>
                        <td>Growth Rate</td>
                        <td class="{{ $data['revenue']['growth_rate'] >= 0 ? 'growth-positive' : 'growth-negative' }}">
                            {{ $data['revenue']['growth_rate'] >= 0 ? '+' : '' }}{{ number_format($data['revenue']['growth_rate'], 1) }}%
                        </td>
                    </tr>
                @endif
            </table>
        </div>
    @endif

    @if(isset($data['engagement']))
        <div class="data-section">
            <h3>User Engagement</h3>
            <table>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Average Daily Active Users</td>
                    <td>{{ number_format($data['engagement']['average_daily_active_users']) }}</td>
                </tr>
                <tr>
                    <td>New Registrations</td>
                    <td>{{ number_format($data['engagement']['total_new_registrations']) }}</td>
                </tr>
                <tr>
                    <td>Jobs Posted</td>
                    <td>{{ number_format($data['engagement']['total_jobs_posted']) }}</td>
                </tr>
                <tr>
                    <td>Skills Posted</td>
                    <td>{{ number_format($data['engagement']['total_skills_posted']) }}</td>
                </tr>
                <tr>
                    <td>Messages Sent</td>
                    <td>{{ number_format($data['engagement']['total_messages_sent']) }}</td>
                </tr>
                <tr>
                    <td>Reviews Created</td>
                    <td>{{ number_format($data['engagement']['total_reviews_created']) }}</td>
                </tr>
            </table>
        </div>
    @endif

    @if(isset($data['key_metrics']))
        <div class="data-section">
            <h3>Key Performance Indicators</h3>
            <table>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Total Users</td>
                    <td>{{ number_format($data['key_metrics']['total_users']) }}</td>
                </tr>
                <tr>
                    <td>Active Users</td>
                    <td>{{ number_format($data['key_metrics']['active_users']) }}</td>
                </tr>
                <tr>
                    <td>User Activity Rate</td>
                    <td>{{ number_format($data['key_metrics']['user_activity_rate'], 1) }}%</td>
                </tr>
                <tr>
                    <td>Total Jobs</td>
                    <td>{{ number_format($data['key_metrics']['total_jobs']) }}</td>
                </tr>
                <tr>
                    <td>Completed Jobs</td>
                    <td>{{ number_format($data['key_metrics']['completed_jobs']) }}</td>
                </tr>
                <tr>
                    <td>Job Completion Rate</td>
                    <td>{{ number_format($data['key_metrics']['job_completion_rate'], 1) }}%</td>
                </tr>
                <tr>
                    <td>Average Job Value</td>
                    <td>${{ number_format($data['key_metrics']['average_job_value'], 2) }}</td>
                </tr>
            </table>
        </div>
    @endif

    @if(isset($data['system_health']))
        <div class="data-section">
            <h3>System Performance</h3>
            <table>
                <tr>
                    <th>Metric</th>
                    <th>Current</th>
                    <th>24h Average</th>
                </tr>
                <tr>
                    <td>Response Time</td>
                    <td>{{ number_format($data['system_health']['current']['response_time'], 2) }}ms</td>
                    <td>{{ number_format($data['system_health']['trends']['average_response_time'], 2) }}ms</td>
                </tr>
                <tr>
                    <td>Error Rate</td>
                    <td>{{ number_format($data['system_health']['current']['error_rate'], 2) }}%</td>
                    <td>{{ number_format($data['system_health']['trends']['average_error_rate'], 2) }}%</td>
                </tr>
                <tr>
                    <td>CPU Usage</td>
                    <td>{{ number_format($data['system_health']['current']['cpu_usage'], 1) }}%</td>
                    <td>Peak: {{ number_format($data['system_health']['trends']['peak_cpu_usage'], 1) }}%</td>
                </tr>
                <tr>
                    <td>Memory Usage</td>
                    <td>{{ number_format($data['system_health']['current']['memory_usage'], 1) }}%</td>
                    <td>Peak: {{ number_format($data['system_health']['trends']['peak_memory_usage'], 1) }}%</td>
                </tr>
            </table>
        </div>
    @endif

    @if(isset($data['forecasting']))
        <div class="data-section">
            <h3>Forecasting</h3>
            <table>
                <tr>
                    <th>Metric</th>
                    <th>Forecast (Next 30 Days)</th>
                    <th>Confidence</th>
                </tr>
                <tr>
                    <td>Revenue Forecast</td>
                    <td>${{ number_format($data['forecasting']['next_month_revenue_forecast'], 2) }}</td>
                    <td>{{ number_format($data['forecasting']['confidence_level'] * 100, 0) }}%</td>
                </tr>
                <tr>
                    <td>User Growth Forecast</td>
                    <td>{{ number_format($data['forecasting']['user_growth_forecast']) }} new users</td>
                    <td>{{ number_format($data['forecasting']['confidence_level'] * 100, 0) }}%</td>
                </tr>
            </table>
        </div>
    @endif

    <div class="data-section">
        <h3>Report Details</h3>
        <p><strong>Report ID:</strong> {{ $report->id }}</p>
        <p><strong>Scheduled Report:</strong> {{ $scheduledReport->name }}</p>
        <p><strong>Frequency:</strong> {{ ucfirst($scheduledReport->frequency) }}</p>
        @if($report->file_path)
            <p><strong>Attachment:</strong> Detailed report data is attached as JSON file</p>
        @endif
    </div>

    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 0.9em; color: #666;">
        <p>This is an automated report from {{ config('app.name') }}. For questions or support, please contact your system administrator.</p>
    </div>
</body>
</html>