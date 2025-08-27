<?php

namespace App\Console\Commands;

use App\Services\QueueMonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class QueueHealthCheck extends Command
{
    protected $signature = 'queue:health-check 
                           {--alert : Send alerts if issues are found}
                           {--json : Output results in JSON format}
                           {--fix : Attempt to fix issues automatically}';

    protected $description = 'Perform comprehensive queue health check and monitoring';

    public function handle(QueueMonitoringService $monitor): int
    {
        $this->info('Performing queue health check...');

        try {
            $healthCheck = $monitor->performHealthCheck();

            if ($this->option('json')) {
                $this->line(json_encode($healthCheck, JSON_PRETTY_PRINT));

                return 0;
            }

            $this->displayHealthResults($healthCheck);

            if ($this->option('fix') && ! empty($healthCheck['alerts'])) {
                $this->attemptAutoFix($monitor, $healthCheck['alerts']);
            }

            // Return exit code based on overall status
            return match ($healthCheck['overall_status']) {
                'healthy' => 0,
                'warning' => 1,
                'critical' => 2,
                default => 1,
            };

        } catch (\Exception $e) {
            $this->error("Health check failed: {$e->getMessage()}");
            Log::error('Queue health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 3;
        }
    }

    protected function displayHealthResults(array $healthCheck): void
    {
        $status = $healthCheck['overall_status'];
        $statusIcon = match ($status) {
            'healthy' => '✅',
            'warning' => '⚠️',
            'critical' => '❌',
            default => '❓',
        };

        $this->line('');
        $this->info("Queue System Health: {$statusIcon} " . strtoupper($status));
        $this->line('Checked at: ' . $healthCheck['checked_at']);
        $this->line('');

        // Display queue statistics
        $this->displayQueueStats($healthCheck['stats']);

        // Display Redis health
        $this->displayRedisHealth($healthCheck['redis_health']);

        // Display worker health
        $this->displayWorkerHealth($healthCheck['worker_health']);

        // Display alerts
        if (! empty($healthCheck['alerts'])) {
            $this->displayAlerts($healthCheck['alerts']);
        } else {
            $this->info('✅ No issues detected');
        }
    }

    protected function displayQueueStats(array $stats): void
    {
        $this->info('Queue Statistics:');
        $this->line('================');

        $headers = ['Queue', 'Pending', 'Processing', 'Failed', 'Avg Time (s)', 'Status'];
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
                number_format($queueStats['avg_processing_time'], 1),
                $statusIcon . ' ' . $queueStats['health_status']['status'],
            ];
        }

        $this->table($headers, $rows);
        $this->line('');
    }

    protected function displayRedisHealth(array $redisHealth): void
    {
        $this->info('Redis Health:');
        $this->line('=============');

        if ($redisHealth['healthy']) {
            $this->info('✅ Redis connection: Healthy');
            if (isset($redisHealth['memory_usage'])) {
                $memoryIcon = $redisHealth['memory_usage'] > 90 ? '⚠️' : '✅';
                $this->line("{$memoryIcon} Memory usage: {$redisHealth['memory_usage']}%");
            }
            if (isset($redisHealth['connected_clients'])) {
                $this->line("👥 Connected clients: {$redisHealth['connected_clients']}");
            }
        } else {
            $this->error('❌ Redis connection: Failed');
            $this->error("Error: {$redisHealth['error']}");
        }

        $this->line('');
    }

    protected function displayWorkerHealth(array $workerHealth): void
    {
        $this->info('Worker Health:');
        $this->line('==============');

        if ($workerHealth['healthy']) {
            $this->info('✅ Worker processes: Healthy');
        } else {
            $this->warn('⚠️ Worker processes: Insufficient');
        }

        $this->line("Active workers: {$workerHealth['active_workers']}");
        $this->line("Recommended workers: {$workerHealth['recommended_workers']}");

        if (isset($workerHealth['total_pending_jobs'])) {
            $this->line("Total pending jobs: {$workerHealth['total_pending_jobs']}");
        }

        $this->line('');
    }

    protected function displayAlerts(array $alerts): void
    {
        $this->warn('Issues Detected:');
        $this->line('================');

        foreach ($alerts as $alert) {
            $icon = $alert['status'] === 'critical' ? '❌' : '⚠️';
            $this->line("{$icon} Queue: {$alert['queue']} - Status: {$alert['status']}");

            if (! empty($alert['issues'])) {
                foreach ($alert['issues'] as $issue) {
                    $this->line('   • ' . $this->formatIssue($issue));
                }
            }

            if (isset($alert['pending'])) {
                $this->line("   Pending: {$alert['pending']} jobs");
            }

            if (isset($alert['failed'])) {
                $this->line("   Failed: {$alert['failed']} jobs");
            }

            if (isset($alert['error'])) {
                $this->line("   Error: {$alert['error']}");
            }

            $this->line('');
        }
    }

    protected function formatIssue(string $issue): string
    {
        return match ($issue) {
            'critical_pending_jobs' => 'Critical number of pending jobs',
            'high_pending_jobs' => 'High number of pending jobs',
            'critical_failed_jobs' => 'Critical number of failed jobs',
            'high_failed_jobs' => 'High number of failed jobs',
            'slow_processing' => 'Slow job processing times',
            'stalled_queue' => 'Queue appears to be stalled',
            'redis_connection_failed' => 'Redis connection failed',
            'insufficient_workers' => 'Insufficient worker processes',
            default => ucfirst(str_replace('_', ' ', $issue)),
        };
    }

    protected function attemptAutoFix(QueueMonitoringService $monitor, array $alerts): void
    {
        $this->info('Attempting automatic fixes...');
        $this->line('');

        foreach ($alerts as $alert) {
            if ($alert['queue'] === 'redis') {
                $this->warn('❌ Cannot auto-fix Redis connection issues');

                continue;
            }

            if (in_array('high_failed_jobs', $alert['issues'] ?? []) ||
                in_array('critical_failed_jobs', $alert['issues'] ?? [])) {

                if ($this->confirm("Retry failed jobs for queue '{$alert['queue']}'?")) {
                    $retriedCount = $monitor->retryFailedJobs($alert['queue']);
                    $this->info("✅ Retried {$retriedCount} failed jobs for queue '{$alert['queue']}'");
                }
            }

            if (in_array('insufficient_workers', $alert['issues'] ?? [])) {
                $this->warn('⚠️ Worker scaling requires manual intervention or process manager configuration');
                $this->line('Consider running: php artisan queue:work --queue=high,default,emails,payments,search,analytics,cleanup,reports,low');
            }
        }
    }
}
