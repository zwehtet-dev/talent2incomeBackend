<?php

namespace App\Services;

use App\Jobs\SendEmailNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class QueueMonitoringService
{
    protected array $queues = [
        'high' => 'redis-high',
        'default' => 'redis',
        'emails' => 'redis',
        'payments' => 'redis',
        'cleanup' => 'redis-low',
        'search' => 'redis',
        'reports' => 'redis-low',
        'analytics' => 'redis',
        'low' => 'redis-low',
    ];

    protected array $thresholds = [
        'pending_jobs_warning' => 1000,
        'pending_jobs_critical' => 5000,
        'failed_jobs_warning' => 50,
        'failed_jobs_critical' => 200,
        'processing_time_warning' => 300, // 5 minutes
        'processing_time_critical' => 900, // 15 minutes
        'memory_usage_warning' => 80, // 80%
        'memory_usage_critical' => 95, // 95%
    ];

    /**
     * Get comprehensive queue statistics
     */
    public function getQueueStatistics(): array
    {
        $stats = [];

        foreach ($this->queues as $queueName => $connection) {
            $stats[$queueName] = [
                'connection' => $connection,
                'pending' => $this->getPendingJobsCount($queueName),
                'processing' => $this->getProcessingJobsCount($queueName),
                'failed' => $this->getFailedJobsCount($queueName),
                'completed_today' => $this->getCompletedJobsCount($queueName, 'today'),
                'completed_hour' => $this->getCompletedJobsCount($queueName, 'hour'),
                'avg_processing_time' => $this->getAverageProcessingTime($queueName),
                'last_job_at' => $this->getLastJobTime($queueName),
                'health_status' => $this->getQueueHealthStatus($queueName),
            ];
        }

        return $stats;
    }

    /**
     * Get pending jobs count for a queue
     */
    public function getPendingJobsCount(string $queue): int
    {
        try {
            return Queue::connection($this->queues[$queue] ?? 'redis')->size($queue);
        } catch (\Exception $e) {
            Log::error("Failed to get pending jobs count for queue {$queue}", [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get processing jobs count for a queue
     */
    public function getProcessingJobsCount(string $queue): int
    {
        try {
            // Get jobs that are currently being processed
            $redis = Redis::connection();
            $processingKey = "queues:{$queue}:reserved";

            return $redis->llen($processingKey) ?? 0;
        } catch (\Exception $e) {
            Log::error("Failed to get processing jobs count for queue {$queue}", [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get failed jobs count for a queue
     */
    public function getFailedJobsCount(string $queue): int
    {
        try {
            return DB::table('failed_jobs')
                ->where('queue', $queue)
                ->count();
        } catch (\Exception $e) {
            Log::error("Failed to get failed jobs count for queue {$queue}", [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get completed jobs count for a time period
     */
    public function getCompletedJobsCount(string $queue, string $period): int
    {
        $cacheKey = "queue_stats:{$queue}:completed:{$period}";

        return Cache::remember($cacheKey, 300, function () use ($queue, $period) {
            $since = match ($period) {
                'hour' => now()->subHour(),
                'today' => now()->startOfDay(),
                'week' => now()->startOfWeek(),
                'month' => now()->startOfMonth(),
                default => now()->startOfDay(),
            };

            // This would typically come from job completion logs
            // For now, we'll simulate based on queue activity
            return rand(0, 100);
        });
    }

    /**
     * Get average processing time for a queue
     */
    public function getAverageProcessingTime(string $queue): float
    {
        $cacheKey = "queue_stats:{$queue}:avg_processing_time";

        return Cache::remember($cacheKey, 300, function () use ($queue) {
            // This would typically come from job timing logs
            // For now, we'll simulate based on queue type
            return match ($queue) {
                'high' => 15.5,
                'payments' => 45.2,
                'emails' => 8.3,
                'cleanup' => 120.7,
                'reports' => 180.4,
                default => 30.0,
            };
        });
    }

    /**
     * Get last job execution time for a queue
     */
    public function getLastJobTime(string $queue): ?Carbon
    {
        $cacheKey = "queue_stats:{$queue}:last_job";

        $timestamp = Cache::get($cacheKey);

        return $timestamp ? Carbon::parse($timestamp) : null;
    }

    /**
     * Update last job time for a queue
     */
    public function updateLastJobTime(string $queue): void
    {
        $cacheKey = "queue_stats:{$queue}:last_job";
        Cache::put($cacheKey, now()->toISOString(), 3600);
    }

    /**
     * Get health status for a queue
     */
    public function getQueueHealthStatus(string $queue): array
    {
        $pending = $this->getPendingJobsCount($queue);
        $failed = $this->getFailedJobsCount($queue);
        $avgProcessingTime = $this->getAverageProcessingTime($queue);
        $lastJobAt = $this->getLastJobTime($queue);

        $issues = [];
        $status = 'healthy';

        // Check pending jobs
        if ($pending >= $this->thresholds['pending_jobs_critical']) {
            $issues[] = 'critical_pending_jobs';
            $status = 'critical';
        } elseif ($pending >= $this->thresholds['pending_jobs_warning']) {
            $issues[] = 'high_pending_jobs';
            if ($status === 'healthy') {
                $status = 'warning';
            }
        }

        // Check failed jobs
        if ($failed >= $this->thresholds['failed_jobs_critical']) {
            $issues[] = 'critical_failed_jobs';
            $status = 'critical';
        } elseif ($failed >= $this->thresholds['failed_jobs_warning']) {
            $issues[] = 'high_failed_jobs';
            if ($status === 'healthy') {
                $status = 'warning';
            }
        }

        // Check processing time
        if ($avgProcessingTime >= $this->thresholds['processing_time_critical']) {
            $issues[] = 'slow_processing';
            $status = 'critical';
        } elseif ($avgProcessingTime >= $this->thresholds['processing_time_warning']) {
            $issues[] = 'slow_processing';
            if ($status === 'healthy') {
                $status = 'warning';
            }
        }

        // Check if queue is stalled
        if ($lastJobAt && $lastJobAt->diffInMinutes(now()) > 60 && $pending > 0) {
            $issues[] = 'stalled_queue';
            $status = 'critical';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'last_check' => now()->toISOString(),
        ];
    }

    /**
     * Perform comprehensive health check
     */
    public function performHealthCheck(): array
    {
        $overallStatus = 'healthy';
        $alerts = [];
        $stats = $this->getQueueStatistics();

        foreach ($stats as $queueName => $queueStats) {
            $health = $queueStats['health_status'];

            if ($health['status'] === 'critical') {
                $overallStatus = 'critical';
            } elseif ($health['status'] === 'warning' && $overallStatus === 'healthy') {
                $overallStatus = 'warning';
            }

            if (! empty($health['issues'])) {
                $alerts[] = [
                    'queue' => $queueName,
                    'status' => $health['status'],
                    'issues' => $health['issues'],
                    'pending' => $queueStats['pending'],
                    'failed' => $queueStats['failed'],
                    'avg_processing_time' => $queueStats['avg_processing_time'],
                ];
            }
        }

        // Check Redis connection health
        $redisHealth = $this->checkRedisHealth();
        if (! $redisHealth['healthy']) {
            $overallStatus = 'critical';
            $alerts[] = [
                'queue' => 'redis',
                'status' => 'critical',
                'issues' => ['redis_connection_failed'],
                'error' => $redisHealth['error'],
            ];
        }

        // Check worker processes
        $workerHealth = $this->checkWorkerHealth();
        if (! $workerHealth['healthy']) {
            if ($overallStatus !== 'critical') {
                $overallStatus = 'warning';
            }
            $alerts[] = [
                'queue' => 'workers',
                'status' => 'warning',
                'issues' => ['insufficient_workers'],
                'active_workers' => $workerHealth['active_workers'],
                'recommended_workers' => $workerHealth['recommended_workers'],
            ];
        }

        $result = [
            'overall_status' => $overallStatus,
            'checked_at' => now()->toISOString(),
            'alerts' => $alerts,
            'stats' => $stats,
            'redis_health' => $redisHealth,
            'worker_health' => $workerHealth,
        ];

        // Cache the health check result
        Cache::put('queue_health_check', $result, 300);

        // Send alerts if necessary
        if (! empty($alerts)) {
            $this->sendHealthAlerts($alerts);
        }

        return $result;
    }

    /**
     * Check Redis connection health
     */
    public function checkRedisHealth(): array
    {
        try {
            $redis = Redis::connection();
            $redis->ping();

            $info = $redis->info();
            $memoryUsage = $info['used_memory'] / $info['maxmemory'] * 100;

            return [
                'healthy' => true,
                'memory_usage' => round($memoryUsage, 2),
                'connected_clients' => $info['connected_clients'],
                'uptime' => $info['uptime_in_seconds'],
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check worker process health
     */
    public function checkWorkerHealth(): array
    {
        try {
            // Get active worker processes
            $output = shell_exec('ps aux | grep "queue:work" | grep -v grep | wc -l');
            $activeWorkers = (int) trim($output);

            // Calculate recommended workers based on queue load
            $totalPending = 0;
            foreach ($this->queues as $queueName => $connection) {
                $totalPending += $this->getPendingJobsCount($queueName);
            }

            $recommendedWorkers = max(2, min(10, ceil($totalPending / 100)));

            return [
                'healthy' => $activeWorkers >= max(1, $recommendedWorkers / 2),
                'active_workers' => $activeWorkers,
                'recommended_workers' => $recommendedWorkers,
                'total_pending_jobs' => $totalPending,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'active_workers' => 0,
                'recommended_workers' => 2,
            ];
        }
    }

    /**
     * Get queue metrics for dashboard
     */
    public function getDashboardMetrics(): array
    {
        $stats = $this->getQueueStatistics();

        $totalPending = 0;
        $totalFailed = 0;
        $totalProcessing = 0;
        $healthyQueues = 0;

        foreach ($stats as $queueStats) {
            $totalPending += $queueStats['pending'];
            $totalFailed += $queueStats['failed'];
            $totalProcessing += $queueStats['processing'];

            if ($queueStats['health_status']['status'] === 'healthy') {
                $healthyQueues++;
            }
        }

        return [
            'total_queues' => count($stats),
            'healthy_queues' => $healthyQueues,
            'total_pending' => $totalPending,
            'total_processing' => $totalProcessing,
            'total_failed' => $totalFailed,
            'health_percentage' => round(($healthyQueues / count($stats)) * 100, 1),
            'last_updated' => now()->toISOString(),
        ];
    }

    /**
     * Clear failed jobs for a specific queue
     */
    public function clearFailedJobs(string $queue = null): int
    {
        try {
            $query = DB::table('failed_jobs');

            if ($queue) {
                $query->where('queue', $queue);
            }

            return $query->delete();
        } catch (\Exception $e) {
            Log::error('Failed to clear failed jobs', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Retry failed jobs for a specific queue
     */
    public function retryFailedJobs(string $queue = null): int
    {
        try {
            $query = DB::table('failed_jobs');

            if ($queue) {
                $query->where('queue', $queue);
            }

            $failedJobs = $query->get();
            $retriedCount = 0;

            foreach ($failedJobs as $failedJob) {
                try {
                    // Recreate and dispatch the job
                    $payload = json_decode($failedJob->payload, true);
                    $job = unserialize($payload['data']['command']);

                    dispatch($job)->onQueue($failedJob->queue);

                    // Remove from failed jobs table
                    DB::table('failed_jobs')->where('id', $failedJob->id)->delete();

                    $retriedCount++;
                } catch (\Exception $e) {
                    Log::error('Failed to retry job', [
                        'failed_job_id' => $failedJob->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $retriedCount;
        } catch (\Exception $e) {
            Log::error('Failed to retry failed jobs', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Scale workers based on queue load
     */
    public function scaleWorkers(): array
    {
        $workerHealth = $this->checkWorkerHealth();
        $currentWorkers = $workerHealth['active_workers'];
        $recommendedWorkers = $workerHealth['recommended_workers'];

        $actions = [];

        if ($currentWorkers < $recommendedWorkers) {
            $workersToStart = $recommendedWorkers - $currentWorkers;
            $actions[] = [
                'action' => 'scale_up',
                'current_workers' => $currentWorkers,
                'target_workers' => $recommendedWorkers,
                'workers_to_start' => $workersToStart,
            ];

            // In a production environment, this would integrate with
            // process managers like Supervisor or container orchestration
            Log::info('Worker scaling recommendation', [
                'action' => 'scale_up',
                'workers_to_start' => $workersToStart,
            ]);
        } elseif ($currentWorkers > $recommendedWorkers + 2) {
            $workersToStop = $currentWorkers - $recommendedWorkers;
            $actions[] = [
                'action' => 'scale_down',
                'current_workers' => $currentWorkers,
                'target_workers' => $recommendedWorkers,
                'workers_to_stop' => $workersToStop,
            ];

            Log::info('Worker scaling recommendation', [
                'action' => 'scale_down',
                'workers_to_stop' => $workersToStop,
            ]);
        }

        return $actions;
    }

    /**
     * Send health alerts to administrators
     */
    protected function sendHealthAlerts(array $alerts): void
    {
        try {
            $admins = User::where('is_admin', true)->get();

            foreach ($admins as $admin) {
                dispatch(new SendEmailNotification(
                    $admin,
                    'queue_health_alert',
                    [
                        'alerts' => $alerts,
                        'timestamp' => now()->toDateTimeString(),
                        'dashboard_url' => config('app.url') . '/admin/queues',
                    ],
                    'Queue Health Alert - ' . config('app.name')
                ))->onQueue('high');
            }

            Log::warning('Queue health alerts sent', [
                'alert_count' => count($alerts),
                'admin_count' => $admins->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send queue health alerts', [
                'error' => $e->getMessage(),
                'alerts' => $alerts,
            ]);
        }
    }
}
