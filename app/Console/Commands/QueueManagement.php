<?php

namespace App\Console\Commands;

use App\Services\QueueManagementService;
use App\Services\QueueMonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class QueueManagement extends Command
{
    protected $signature = 'queue:manage 
                           {action : Action to perform (cycle|status|config|alerts|metrics)}
                           {--force : Force action without confirmation}
                           {--json : Output results in JSON format}
                           {--config-file= : Path to configuration file}';

    protected $description = 'Comprehensive queue management with monitoring, scaling, and recovery';

    public function handle(
        QueueManagementService $management,
        QueueMonitoringService $monitoring
    ): int {
        $action = $this->argument('action');

        try {
            return match ($action) {
                'cycle' => $this->performManagementCycle($management),
                'status' => $this->showStatus($management),
                'config' => $this->showConfiguration(),
                'alerts' => $this->showAlerts($management),
                'metrics' => $this->showMetrics($monitoring),
                default => $this->error("Unknown action: {$action}") ?: 1,
            };
        } catch (\Exception $e) {
            $this->error("Queue management failed: {$e->getMessage()}");
            Log::error('Queue management command failed', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return 1;
        }
    }

    /**
     * Perform complete management cycle
     */
    protected function performManagementCycle(QueueManagementService $management): int
    {
        $this->info('Starting queue management cycle...');
        $this->line('');

        if (! $this->option('force')) {
            if (! $this->confirm('Perform complete queue management cycle?')) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        $results = $management->performManagementCycle();

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));

            return 0;
        }

        $this->displayManagementResults($results);

        // Return appropriate exit code
        if (isset($results['error'])) {
            return 1;
        }

        if (isset($results['health_check']['overall_status'])) {
            return match ($results['health_check']['overall_status']) {
                'healthy' => 0,
                'warning' => 1,
                'critical' => 2,
                default => 1,
            };
        }

        return 0;
    }

    /**
     * Display management cycle results
     */
    protected function displayManagementResults(array $results): void
    {
        $this->info('Queue Management Cycle Results');
        $this->line('================================');
        $this->line('Completed at: ' . $results['timestamp']);
        $this->line('');

        // Health Check Results
        if (isset($results['health_check'])) {
            $this->displayHealthCheckResults($results['health_check']);
        }

        // Auto Recovery Results
        if (isset($results['auto_recovery']) && ! empty($results['auto_recovery'])) {
            $this->displayAutoRecoveryResults($results['auto_recovery']);
        }

        // Scaling Results
        if (isset($results['scaling']) && ! empty($results['scaling'])) {
            $this->displayScalingResults($results['scaling']);
        }

        // Cleanup Results
        if (isset($results['cleanup']) && ! empty($results['cleanup'])) {
            $this->displayCleanupResults($results['cleanup']);
        }

        // Alert Results
        if (isset($results['alerts']) && ! empty($results['alerts'])) {
            $this->displayAlertResults($results['alerts']);
        }

        // Error Results
        if (isset($results['error'])) {
            $this->error('❌ Management cycle failed: ' . $results['error']);
        }
    }

    /**
     * Display health check results
     */
    protected function displayHealthCheckResults(array $healthCheck): void
    {
        $status = $healthCheck['overall_status'];
        $statusIcon = match ($status) {
            'healthy' => '✅',
            'warning' => '⚠️',
            'critical' => '❌',
            default => '❓',
        };

        $this->info("Health Check: {$statusIcon} " . strtoupper($status));

        if (! empty($healthCheck['alerts'])) {
            $this->line('  Issues found: ' . count($healthCheck['alerts']));
            foreach ($healthCheck['alerts'] as $alert) {
                $alertIcon = $alert['status'] === 'critical' ? '❌' : '⚠️';
                $this->line("  {$alertIcon} {$alert['queue']}: " . implode(', ', $alert['issues'] ?? []));
            }
        } else {
            $this->line('  ✅ No issues detected');
        }

        $this->line('');
    }

    /**
     * Display auto recovery results
     */
    protected function displayAutoRecoveryResults(array $recovery): void
    {
        $this->info('Auto Recovery Actions:');

        foreach ($recovery as $action) {
            $icon = $action['status'] === 'success' ? '✅' : '❌';
            $actionName = ucfirst(str_replace('_', ' ', $action['action']));

            if (isset($action['count'])) {
                $this->line("  {$icon} {$actionName}: {$action['count']} items");
            } else {
                $this->line("  {$icon} {$actionName}");
            }

            if (isset($action['error'])) {
                $this->line("    Error: {$action['error']}");
            }
        }

        $this->line('');
    }

    /**
     * Display scaling results
     */
    protected function displayScalingResults(array $scaling): void
    {
        $this->info('Worker Scaling Actions:');

        foreach ($scaling as $action) {
            $actionName = ucfirst(str_replace('_', ' ', $action['action']));
            $this->line("  📊 {$actionName}");

            if (isset($action['current_workers'])) {
                $this->line("    Current: {$action['current_workers']} workers");
            }

            if (isset($action['target_workers'])) {
                $this->line("    Target: {$action['target_workers']} workers");
            }

            if (isset($action['reason'])) {
                $this->line("    Reason: {$action['reason']}");
            }
        }

        $this->line('');
    }

    /**
     * Display cleanup results
     */
    protected function displayCleanupResults(array $cleanup): void
    {
        $this->info('Cleanup Operations:');

        foreach ($cleanup as $operation) {
            $icon = $operation['status'] === 'success' ? '✅' : '❌';
            $actionName = ucfirst(str_replace('_', ' ', $operation['action']));
            $this->line("  {$icon} {$actionName}");

            if (isset($operation['error'])) {
                $this->line("    Error: {$operation['error']}");
            }
        }

        $this->line('');
    }

    /**
     * Display alert results
     */
    protected function displayAlertResults(array $alerts): void
    {
        if (isset($alerts['status']) && $alerts['status'] === 'cooldown_active') {
            $this->line('📢 Alerts: Cooldown period active');

            return;
        }

        $this->info('Alert Actions:');
        $this->line('  📧 Sent ' . count($alerts) . ' alert(s) to administrators');
        $this->line('');
    }

    /**
     * Show comprehensive status
     */
    protected function showStatus(QueueManagementService $management): int
    {
        $this->info('Queue Management Status');
        $this->line('======================');

        $dashboardData = $management->getDashboardData();

        if ($this->option('json')) {
            $this->line(json_encode($dashboardData, JSON_PRETTY_PRINT));

            return 0;
        }

        // Overall Health
        $health = $dashboardData['health_status'];
        $statusIcon = match ($health['overall_status']) {
            'healthy' => '✅',
            'warning' => '⚠️',
            'critical' => '❌',
            default => '❓',
        };

        $this->line("{$statusIcon} Overall Status: " . strtoupper($health['overall_status']));
        $this->line('');

        // Configuration Status
        $config = $dashboardData['configuration'];
        $this->info('Configuration:');
        $this->line('  Monitoring: ' . ($config['monitoring_enabled'] ? '✅ Enabled' : '❌ Disabled'));
        $this->line('  Auto Recovery: ' . ($config['auto_recovery_enabled'] ? '✅ Enabled' : '❌ Disabled'));
        $this->line('  Worker Scaling: ' . ($config['scaling_enabled'] ? '✅ Enabled' : '❌ Disabled'));
        $this->line('  Job Scheduling: ' . ($config['scheduling_enabled'] ? '✅ Enabled' : '❌ Disabled'));
        $this->line('');

        // Metrics Summary
        $metrics = $dashboardData['metrics'];
        $this->info('Current Metrics:');
        $this->line("  Total Queues: {$metrics['total_queues']}");
        $this->line("  Healthy Queues: {$metrics['healthy_queues']} ({$metrics['health_percentage']}%)");
        $this->line('  Pending Jobs: ' . number_format($metrics['total_pending']));
        $this->line('  Processing Jobs: ' . number_format($metrics['total_processing']));
        $this->line('  Failed Jobs: ' . number_format($metrics['total_failed']));
        $this->line('');

        // Worker Status
        $workers = $dashboardData['worker_status'];
        $workerIcon = $workers['healthy'] ? '✅' : '⚠️';
        $this->info('Worker Status:');
        $this->line("  {$workerIcon} Active Workers: {$workers['active_workers']}");
        $this->line("  📊 Recommended Workers: {$workers['recommended_workers']}");
        $this->line('');

        // Redis Status
        $redis = $dashboardData['redis_status'];
        $redisIcon = $redis['healthy'] ? '✅' : '❌';
        $this->info('Redis Status:');
        $this->line("  {$redisIcon} Connection: " . ($redis['healthy'] ? 'Healthy' : 'Failed'));

        if (isset($redis['memory_usage'])) {
            $memoryIcon = $redis['memory_usage'] > 90 ? '⚠️' : '✅';
            $this->line("  {$memoryIcon} Memory Usage: {$redis['memory_usage']}%");
        }

        return $health['overall_status'] === 'healthy' ? 0 : 1;
    }

    /**
     * Show configuration
     */
    protected function showConfiguration(): int
    {
        $this->info('Queue Management Configuration');
        $this->line('==============================');

        $config = config('queue_management');

        if ($this->option('json')) {
            $this->line(json_encode($config, JSON_PRETTY_PRINT));

            return 0;
        }

        // Monitoring Configuration
        $this->info('Monitoring:');
        $this->line('  Enabled: ' . ($config['monitoring']['enabled'] ? 'Yes' : 'No'));
        $this->line('  Health Check Interval: ' . $config['monitoring']['health_check_interval'] . ' seconds');
        $this->line('  Metrics Retention: ' . $config['monitoring']['metrics_retention_days'] . ' days');
        $this->line('');

        // Auto Recovery Configuration
        $this->info('Auto Recovery:');
        $this->line('  Enabled: ' . ($config['auto_recovery']['enabled'] ? 'Yes' : 'No'));
        $this->line('  Max Retries: ' . $config['auto_recovery']['max_retries']);
        $this->line('  Retry Delay: ' . $config['auto_recovery']['retry_delay_minutes'] . ' minutes');
        $this->line('');

        // Scaling Configuration
        $this->info('Worker Scaling:');
        $this->line('  Enabled: ' . ($config['scaling']['enabled'] ? 'Yes' : 'No'));
        $this->line('  Strategy: ' . $config['scaling']['strategy']);

        if (isset($config['scaling']['load_based'])) {
            $loadConfig = $config['scaling']['load_based'];
            $this->line('  Min Workers: ' . $loadConfig['min_workers']);
            $this->line('  Max Workers: ' . $loadConfig['max_workers']);
        }

        $this->line('');

        // Priority Configuration
        $this->info('Queue Priorities:');
        foreach ($config['priorities'] as $priority => $priorityConfig) {
            $this->line("  {$priority}:");
            $this->line("    Weight: {$priorityConfig['weight']}");
            $this->line("    Max Workers: {$priorityConfig['max_workers']}");
            $this->line("    Timeout: {$priorityConfig['timeout']}s");
        }

        return 0;
    }

    /**
     * Show recent alerts
     */
    protected function showAlerts(QueueManagementService $management): int
    {
        $this->info('Recent Queue Alerts');
        $this->line('===================');

        $dashboardData = $management->getDashboardData();
        $alerts = $dashboardData['recent_alerts'] ?? [];

        if (empty($alerts)) {
            $this->info('✅ No recent alerts');

            return 0;
        }

        if ($this->option('json')) {
            $this->line(json_encode($alerts, JSON_PRETTY_PRINT));

            return 0;
        }

        $headers = ['Time', 'Type', 'Queue', 'Status', 'Resolved'];
        $rows = [];

        foreach ($alerts as $alert) {
            $resolvedIcon = $alert->resolved ? '✅' : '❌';
            $statusIcon = match ($alert->status) {
                'critical' => '❌',
                'warning' => '⚠️',
                default => '📢',
            };

            $rows[] = [
                \Carbon\Carbon::parse($alert->created_at)->diffForHumans(),
                $alert->type,
                $alert->queue ?? 'N/A',
                $statusIcon . ' ' . $alert->status,
                $resolvedIcon,
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }

    /**
     * Show metrics
     */
    protected function showMetrics(QueueMonitoringService $monitoring): int
    {
        $this->info('Queue Metrics');
        $this->line('=============');

        $stats = $monitoring->getQueueStatistics();

        if ($this->option('json')) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));

            return 0;
        }

        $headers = ['Queue', 'Pending', 'Processing', 'Failed', 'Completed (24h)', 'Avg Time (s)', 'Status'];
        $rows = [];

        foreach ($stats as $queueName => $queueStats) {
            $statusIcon = match ($queueStats['health_status']['status']) {
                'healthy' => '✅',
                'warning' => '⚠️',
                'critical' => '❌',
                default => '❓',
            };

            $rows[] = [
                $queueName,
                number_format($queueStats['pending']),
                number_format($queueStats['processing']),
                number_format($queueStats['failed']),
                number_format($queueStats['completed_today']),
                number_format($queueStats['avg_processing_time'], 1),
                $statusIcon . ' ' . $queueStats['health_status']['status'],
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }
}
