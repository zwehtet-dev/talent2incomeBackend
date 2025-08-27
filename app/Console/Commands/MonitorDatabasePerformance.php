<?php

namespace App\Console\Commands;

use App\Services\DatabaseConnectionPoolService;
use App\Services\DatabasePerformanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MonitorDatabasePerformance extends Command
{
    protected $signature = 'db:monitor 
                           {--threshold=1000 : Slow query threshold in milliseconds}
                           {--alert : Send alerts for performance issues}
                           {--continuous : Run continuous monitoring}
                           {--interval=60 : Monitoring interval in seconds}';

    protected $description = 'Monitor database performance and send alerts';

    private DatabasePerformanceService $performanceService;
    private DatabaseConnectionPoolService $poolService;
    private array $alertThresholds;

    public function __construct(
        DatabasePerformanceService $performanceService,
        DatabaseConnectionPoolService $poolService
    ) {
        parent::__construct();
        $this->performanceService = $performanceService;
        $this->poolService = $poolService;

        $this->alertThresholds = [
            'connection_usage_percent' => 80,
            'slow_query_percent' => 10,
            'avg_query_time' => 500, // milliseconds
            'pool_utilization' => 90,
        ];
    }

    public function handle(): int
    {
        $this->info('Starting database performance monitoring...');

        if ($this->option('continuous')) {
            $this->runContinuousMonitoring();
        } else {
            $this->runSingleCheck();
        }

        return 0;
    }

    private function runContinuousMonitoring(): void
    {
        $interval = (int) $this->option('interval');
        $this->info("Running continuous monitoring with {$interval}s intervals. Press Ctrl+C to stop.");

        while (true) {
            $this->performMonitoringCheck();
            sleep($interval);
        }
    }

    private function runSingleCheck(): void
    {
        $this->performMonitoringCheck();
    }

    private function performMonitoringCheck(): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $this->line("[{$timestamp}] Performing performance check...");

        $metrics = $this->performanceService->getPerformanceMetrics();
        $poolStats = $this->poolService->getPoolStats();

        $issues = $this->analyzeMetrics($metrics, $poolStats);

        if (! empty($issues)) {
            $this->displayIssues($issues);

            if ($this->option('alert')) {
                $this->sendAlerts($issues, $metrics, $poolStats);
            }
        } else {
            $this->info('✓ All performance metrics are within normal ranges.');
        }

        $this->storeMetrics($metrics, $poolStats);
    }

    private function analyzeMetrics(array $metrics, array $poolStats): array
    {
        $issues = [];

        // Check connection usage
        if (isset($metrics['connection_stats']['connection_usage_percent'])) {
            $usage = $metrics['connection_stats']['connection_usage_percent'];
            if ($usage > $this->alertThresholds['connection_usage_percent']) {
                $issues[] = [
                    'type' => 'high_connection_usage',
                    'severity' => $usage > 95 ? 'critical' : 'warning',
                    'message' => "High connection usage: {$usage}%",
                    'value' => $usage,
                    'threshold' => $this->alertThresholds['connection_usage_percent'],
                ];
            }
        }

        // Check slow query percentage
        if (isset($metrics['query_stats']['slow_queries_percentage'])) {
            $slowPercent = $metrics['query_stats']['slow_queries_percentage'];
            if ($slowPercent > $this->alertThresholds['slow_query_percent']) {
                $issues[] = [
                    'type' => 'high_slow_queries',
                    'severity' => $slowPercent > 25 ? 'critical' : 'warning',
                    'message' => "High slow query percentage: {$slowPercent}%",
                    'value' => $slowPercent,
                    'threshold' => $this->alertThresholds['slow_query_percent'],
                ];
            }
        }

        // Check average query time
        if (isset($metrics['query_stats']['average_execution_time'])) {
            $avgTime = $metrics['query_stats']['average_execution_time'];
            if ($avgTime > $this->alertThresholds['avg_query_time']) {
                $issues[] = [
                    'type' => 'high_avg_query_time',
                    'severity' => $avgTime > 1000 ? 'critical' : 'warning',
                    'message' => "High average query time: {$avgTime}ms",
                    'value' => $avgTime,
                    'threshold' => $this->alertThresholds['avg_query_time'],
                ];
            }
        }

        // Check connection pool utilization
        foreach ($poolStats['health'] as $poolType => $health) {
            if ($health['utilization_percent'] > $this->alertThresholds['pool_utilization']) {
                $issues[] = [
                    'type' => 'high_pool_utilization',
                    'severity' => $health['utilization_percent'] > 95 ? 'critical' : 'warning',
                    'message' => "High {$poolType} pool utilization: {$health['utilization_percent']}%",
                    'value' => $health['utilization_percent'],
                    'threshold' => $this->alertThresholds['pool_utilization'],
                    'pool' => $poolType,
                ];
            }
        }

        // Check for table size issues
        if (isset($metrics['table_stats']) && ! empty($metrics['table_stats'])) {
            foreach ($metrics['table_stats'] as $table) {
                // Alert if table is over 1GB
                if ($table['total_size'] > 1073741824) {
                    $issues[] = [
                        'type' => 'large_table',
                        'severity' => 'info',
                        'message' => "Large table detected: {$table['name']} ({$this->formatBytes($table['total_size'])})",
                        'table' => $table['name'],
                        'size' => $table['total_size'],
                    ];
                }
            }
        }

        return $issues;
    }

    private function displayIssues(array $issues): void
    {
        $this->error('⚠ Performance issues detected:');

        foreach ($issues as $issue) {
            $icon = match ($issue['severity']) {
                'critical' => '🔴',
                'warning' => '🟡',
                'info' => '🔵',
                default => '⚠',
            };

            $this->line("{$icon} [{$issue['severity']}] {$issue['message']}");
        }
    }

    private function sendAlerts(array $issues, array $metrics, array $poolStats): void
    {
        $criticalIssues = array_filter($issues, fn ($issue) => $issue['severity'] === 'critical');

        if (! empty($criticalIssues)) {
            $this->sendCriticalAlert($criticalIssues, $metrics, $poolStats);
        }

        $warningIssues = array_filter($issues, fn ($issue) => $issue['severity'] === 'warning');

        if (! empty($warningIssues)) {
            $this->sendWarningAlert($warningIssues, $metrics, $poolStats);
        }
    }

    private function sendCriticalAlert(array $issues, array $metrics, array $poolStats): void
    {
        $alertKey = 'db_critical_alert_sent_' . date('Y-m-d-H');

        // Prevent spam - only send one critical alert per hour
        if (Cache::has($alertKey)) {
            return;
        }

        $subject = 'CRITICAL: Database Performance Alert';
        $message = $this->buildAlertMessage($issues, $metrics, $poolStats);

        Log::critical('Database performance critical alert', [
            'issues' => $issues,
            'metrics' => $metrics,
        ]);

        // Send email alert (if configured)
        $adminEmails = config('monitoring.admin_emails', []);
        foreach ($adminEmails as $email) {
            try {
                Mail::raw($message, function ($mail) use ($email, $subject) {
                    $mail->to($email)->subject($subject);
                });
            } catch (\Exception $e) {
                Log::error('Failed to send critical alert email', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Cache::put($alertKey, true, 3600); // Cache for 1 hour
    }

    private function sendWarningAlert(array $issues, array $metrics, array $poolStats): void
    {
        $alertKey = 'db_warning_alert_sent_' . date('Y-m-d-H');

        // Prevent spam - only send one warning alert per hour
        if (Cache::has($alertKey)) {
            return;
        }

        Log::warning('Database performance warning', [
            'issues' => $issues,
            'metrics' => $metrics,
        ]);

        Cache::put($alertKey, true, 3600); // Cache for 1 hour
    }

    private function buildAlertMessage(array $issues, array $metrics, array $poolStats): string
    {
        $message = "Database Performance Alert\n";
        $message .= "========================\n\n";
        $message .= 'Timestamp: ' . now()->toDateTimeString() . "\n";
        $message .= 'Server: ' . gethostname() . "\n\n";

        $message .= "Issues Detected:\n";
        foreach ($issues as $issue) {
            $message .= "- [{$issue['severity']}] {$issue['message']}\n";
        }

        $message .= "\nCurrent Metrics:\n";
        if (isset($metrics['connection_stats'])) {
            $message .= '- Connection Usage: ' . round($metrics['connection_stats']['connection_usage_percent'] ?? 0, 2) . "%\n";
        }

        if (isset($metrics['query_stats'])) {
            $message .= '- Average Query Time: ' . round($metrics['query_stats']['average_execution_time'] ?? 0, 2) . "ms\n";
            $message .= '- Slow Query Percentage: ' . round($metrics['query_stats']['slow_queries_percentage'] ?? 0, 2) . "%\n";
        }

        $message .= "\nConnection Pool Status:\n";
        foreach ($poolStats['health'] as $poolType => $health) {
            $message .= "- {$poolType}: {$health['utilization_percent']}% utilization, {$health['available_connections']} available\n";
        }

        $message .= "\nPlease investigate and take appropriate action.\n";

        return $message;
    }

    private function storeMetrics(array $metrics, array $poolStats): void
    {
        // Store metrics in database for trend analysis
        try {
            \DB::table('database_performance_metrics')->insert([
                'timestamp' => now(),
                'connection_usage_percent' => $metrics['connection_stats']['connection_usage_percent'] ?? null,
                'avg_query_time' => $metrics['query_stats']['average_execution_time'] ?? null,
                'slow_query_percent' => $metrics['query_stats']['slow_queries_percentage'] ?? null,
                'total_queries' => $metrics['query_stats']['total_queries'] ?? null,
                'pool_stats' => json_encode($poolStats),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store performance metrics', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
