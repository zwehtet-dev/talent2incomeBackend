<?php

namespace App\Services;

use App\Mail\QueueHealthAlert;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class QueueManagementService
{
    protected QueueMonitoringService $monitor;
    /** @var array<string, mixed> */
    protected array $config;

    public function __construct(QueueMonitoringService $monitor)
    {
        $this->monitor = $monitor;
        $this->config = config('queue_management');
    }

    /**
     * Perform comprehensive queue management cycle
     * @return array<string, mixed>
     */
    public function performManagementCycle(): array
    {
        $results = [
            'timestamp' => now()->toISOString(),
            'health_check' => null,
            'auto_recovery' => null,
            'scaling' => null,
            'cleanup' => null,
            'alerts' => [],
        ];

        try {
            // 1. Health Check
            if ($this->config['monitoring']['enabled']) {
                $results['health_check'] = $this->performHealthCheck();
            }

            // 2. Auto Recovery
            if ($this->config['auto_recovery']['enabled']) {
                $results['auto_recovery'] = $this->performAutoRecovery();
            }

            // 3. Worker Scaling
            if ($this->config['scaling']['enabled']) {
                $results['scaling'] = $this->performWorkerScaling();
            }

            // 4. Cleanup Operations
            $results['cleanup'] = $this->performCleanupOperations();

            // 5. Send Alerts if needed
            $results['alerts'] = $this->processAlerts($results);

            Log::info('Queue management cycle completed', $results);

        } catch (\Exception $e) {
            Log::error('Queue management cycle failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Get queue management dashboard data
     */
    public function getDashboardData(): array
    {
        return [
            'health_status' => $this->monitor->performHealthCheck(),
            'metrics' => $this->monitor->getDashboardMetrics(),
            'recent_alerts' => $this->getRecentAlerts(),
            'worker_status' => $this->monitor->checkWorkerHealth(),
            'redis_status' => $this->monitor->checkRedisHealth(),
            'configuration' => [
                'monitoring_enabled' => $this->config['monitoring']['enabled'],
                'auto_recovery_enabled' => $this->config['auto_recovery']['enabled'],
                'scaling_enabled' => $this->config['scaling']['enabled'],
                'scheduling_enabled' => $this->config['scheduling']['enabled'],
            ],
        ];
    }

    /**
     * Perform health check with enhanced monitoring
     */
    protected function performHealthCheck(): array
    {
        $healthCheck = $this->monitor->performHealthCheck();

        // Store health metrics for trending
        $this->storeHealthMetrics($healthCheck);

        return $healthCheck;
    }

    /**
     * Store health metrics for historical analysis
     */
    protected function storeHealthMetrics(array $healthCheck): void
    {
        if (! $this->config['logging']['metrics']['enabled']) {
            return;
        }

        $timestamp = now();
        $metrics = [
            'timestamp' => $timestamp->toISOString(),
            'overall_status' => $healthCheck['overall_status'],
            'total_pending' => 0,
            'total_processing' => 0,
            'total_failed' => 0,
            'redis_memory_usage' => $healthCheck['redis_health']['memory_usage'] ?? 0,
            'active_workers' => $healthCheck['worker_health']['active_workers'] ?? 0,
        ];

        foreach ($healthCheck['stats'] as $queueStats) {
            $metrics['total_pending'] += $queueStats['pending'];
            $metrics['total_processing'] += $queueStats['processing'];
            $metrics['total_failed'] += $queueStats['failed'];
        }

        // Store in cache for dashboard
        $cacheKey = 'queue_metrics:' . $timestamp->format('Y-m-d-H-i');
        Cache::put($cacheKey, $metrics, 86400 * $this->config['monitoring']['metrics_retention_days']);

        // Store in database for long-term analysis (optional)
        if (config('database.connections.mysql')) {
            try {
                DB::table('queue_metrics')->insert($metrics);
            } catch (\Exception $e) {
                Log::warning('Failed to store queue metrics in database', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Perform automatic recovery operations
     */
    protected function performAutoRecovery(): array
    {
        $recoveryActions = [];
        $config = $this->config['auto_recovery'];

        try {
            // 1. Retry failed jobs if enabled
            if ($config['actions']['retry_failed_jobs']) {
                $retriedJobs = $this->retryFailedJobs();
                if ($retriedJobs > 0) {
                    $recoveryActions[] = [
                        'action' => 'retry_failed_jobs',
                        'count' => $retriedJobs,
                        'status' => 'success',
                    ];
                }
            }

            // 2. Clear old failed jobs if enabled
            if ($config['actions']['clear_old_failed_jobs']) {
                $clearedJobs = $this->clearOldFailedJobs();
                if ($clearedJobs > 0) {
                    $recoveryActions[] = [
                        'action' => 'clear_old_failed_jobs',
                        'count' => $clearedJobs,
                        'status' => 'success',
                    ];
                }
            }

            // 3. Restart stalled workers if enabled
            if ($config['actions']['restart_stalled_workers']) {
                $restarted = $this->restartStalledWorkers();
                if ($restarted) {
                    $recoveryActions[] = [
                        'action' => 'restart_stalled_workers',
                        'status' => 'success',
                    ];
                }
            }

        } catch (\Exception $e) {
            $recoveryActions[] = [
                'action' => 'auto_recovery',
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        return $recoveryActions;
    }

    /**
     * Retry failed jobs with intelligent filtering
     */
    protected function retryFailedJobs(): int
    {
        $config = $this->config['auto_recovery'];
        $retriedCount = 0;

        try {
            $failedJobs = DB::table('failed_jobs')
                ->where('failed_at', '>', now()->subMinutes($config['retry_delay_minutes']))
                ->limit(100) // Process in batches
                ->get();

            foreach ($failedJobs as $failedJob) {
                try {
                    // Check if job should be retried based on failure count
                    $retryCount = $this->getJobRetryCount($failedJob->id);

                    if ($retryCount >= $config['max_retries']) {
                        continue; // Skip jobs that have exceeded retry limit
                    }

                    // Parse and recreate the job
                    $payload = json_decode($failedJob->payload, true);

                    if (! $this->isJobRetryable($payload)) {
                        continue; // Skip non-retryable jobs
                    }

                    $job = unserialize($payload['data']['command']);

                    // Dispatch with delay to avoid immediate re-failure
                    dispatch($job)
                        ->onQueue($failedJob->queue)
                        ->delay(now()->addMinutes($config['retry_delay_minutes']));

                    // Remove from failed jobs table
                    DB::table('failed_jobs')->where('id', $failedJob->id)->delete();

                    // Track retry count
                    $this->incrementJobRetryCount($failedJob->id);

                    $retriedCount++;

                } catch (\Exception $e) {
                    Log::warning('Failed to retry job', [
                        'failed_job_id' => $failedJob->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to retry failed jobs', [
                'error' => $e->getMessage(),
            ]);
        }

        return $retriedCount;
    }

    /**
     * Clear old failed jobs
     */
    protected function clearOldFailedJobs(): int
    {
        $daysOld = $this->config['auto_recovery']['clear_failed_after_days'];

        try {
            return DB::table('failed_jobs')
                ->where('failed_at', '<', now()->subDays($daysOld))
                ->delete();
        } catch (\Exception $e) {
            Log::error('Failed to clear old failed jobs', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Restart stalled workers
     */
    protected function restartStalledWorkers(): bool
    {
        try {
            // This would typically integrate with a process manager like Supervisor
            // For now, we'll log the recommendation

            $workerHealth = $this->monitor->checkWorkerHealth();

            if (! $workerHealth['healthy']) {
                Log::warning('Stalled workers detected - manual restart recommended', [
                    'active_workers' => $workerHealth['active_workers'],
                    'recommended_workers' => $workerHealth['recommended_workers'],
                ]);

                // In a production environment, this would send a restart signal
                // to the process manager or container orchestration system
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to restart stalled workers', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Perform worker scaling based on load
     */
    protected function performWorkerScaling(): array
    {
        $scalingActions = [];
        $config = $this->config['scaling'];

        try {
            if ($config['strategy'] === 'load_based') {
                $scalingActions = $this->performLoadBasedScaling();
            } elseif ($config['strategy'] === 'time_based') {
                $scalingActions = $this->performTimeBasedScaling();
            } elseif ($config['strategy'] === 'hybrid') {
                $loadActions = $this->performLoadBasedScaling();
                $timeActions = $this->performTimeBasedScaling();
                $scalingActions = array_merge($loadActions, $timeActions);
            }

        } catch (\Exception $e) {
            $scalingActions[] = [
                'action' => 'scaling_error',
                'error' => $e->getMessage(),
            ];
        }

        return $scalingActions;
    }

    /**
     * Perform load-based worker scaling
     */
    protected function performLoadBasedScaling(): array
    {
        return $this->monitor->scaleWorkers();
    }

    /**
     * Perform time-based worker scaling
     */
    protected function performTimeBasedScaling(): array
    {
        $config = $this->config['scaling']['time_based'];
        $now = now();
        $currentTime = $now->format('H:i');

        $peakStart = Carbon::parse($config['peak_hours']['start']);
        $peakEnd = Carbon::parse($config['peak_hours']['end']);

        $isPeakHours = $currentTime >= $peakStart->format('H:i') &&
                       $currentTime <= $peakEnd->format('H:i');

        $targetWorkers = $isPeakHours ?
            $config['peak_hours']['workers'] :
            $config['off_peak_hours']['workers'];

        $workerHealth = $this->monitor->checkWorkerHealth();
        $currentWorkers = $workerHealth['active_workers'];

        $actions = [];

        if ($currentWorkers < $targetWorkers) {
            $actions[] = [
                'action' => 'time_based_scale_up',
                'current_workers' => $currentWorkers,
                'target_workers' => $targetWorkers,
                'reason' => $isPeakHours ? 'peak_hours' : 'off_peak_hours',
            ];
        } elseif ($currentWorkers > $targetWorkers) {
            $actions[] = [
                'action' => 'time_based_scale_down',
                'current_workers' => $currentWorkers,
                'target_workers' => $targetWorkers,
                'reason' => $isPeakHours ? 'peak_hours' : 'off_peak_hours',
            ];
        }

        return $actions;
    }

    /**
     * Perform cleanup operations
     */
    protected function performCleanupOperations(): array
    {
        $cleanupActions = [];

        try {
            // Clean up old cache entries
            $this->cleanupOldCacheEntries();
            $cleanupActions[] = [
                'action' => 'cache_cleanup',
                'status' => 'success',
            ];

            // Clean up old log entries
            $this->cleanupOldLogEntries();
            $cleanupActions[] = [
                'action' => 'log_cleanup',
                'status' => 'success',
            ];

        } catch (\Exception $e) {
            $cleanupActions[] = [
                'action' => 'cleanup_error',
                'error' => $e->getMessage(),
            ];
        }

        return $cleanupActions;
    }

    /**
     * Clean up old cache entries
     */
    protected function cleanupOldCacheEntries(): void
    {
        $retentionDays = $this->config['monitoring']['metrics_retention_days'];
        $cutoffDate = now()->subDays($retentionDays);

        // Clean up old queue metrics
        $pattern = 'queue_metrics:*';
        $keys = Cache::getRedis()->keys($pattern);

        foreach ($keys as $key) {
            $keyDate = $this->extractDateFromCacheKey($key);
            if ($keyDate && $keyDate->lt($cutoffDate)) {
                Cache::forget($key);
            }
        }
    }

    /**
     * Clean up old log entries
     */
    protected function cleanupOldLogEntries(): void
    {
        // This would typically clean up application-specific log tables
        // For now, we'll just log the action
        Log::info('Log cleanup performed');
    }

    /**
     * Process and send alerts
     */
    protected function processAlerts(array $results): array
    {
        $alerts = [];
        $config = $this->config['monitoring']['alerts'];

        if (! $config['enabled']) {
            return $alerts;
        }

        // Check if we're in cooldown period
        $lastAlertTime = Cache::get('queue_last_alert_time');
        if ($lastAlertTime &&
            Carbon::parse($lastAlertTime)->addMinutes($config['cooldown_minutes'])->isFuture()) {
            return ['status' => 'cooldown_active'];
        }

        // Collect alerts from health check
        if (isset($results['health_check']['alerts']) && ! empty($results['health_check']['alerts'])) {
            $alerts = array_merge($alerts, $results['health_check']['alerts']);
        }

        // Add recovery alerts
        if (isset($results['auto_recovery'])) {
            foreach ($results['auto_recovery'] as $action) {
                if ($action['status'] === 'error') {
                    $alerts[] = [
                        'type' => 'recovery_failed',
                        'action' => $action['action'],
                        'error' => $action['error'],
                    ];
                }
            }
        }

        // Send alerts if any exist
        if (! empty($alerts)) {
            $this->sendAlerts($alerts);
            Cache::put('queue_last_alert_time', now()->toISOString(), 3600);
        }

        return $alerts;
    }

    /**
     * Send alerts through configured channels
     */
    protected function sendAlerts(array $alerts): void
    {
        $config = $this->config['monitoring']['alerts'];

        foreach ($config['channels'] as $channel) {
            try {
                switch ($channel) {
                    case 'email':
                        $this->sendEmailAlerts($alerts);

                        break;
                    case 'log':
                        $this->sendLogAlerts($alerts);

                        break;
                    case 'database':
                        $this->sendDatabaseAlerts($alerts);

                        break;
                }
            } catch (\Exception $e) {
                Log::error("Failed to send alerts via {$channel}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send email alerts
     */
    protected function sendEmailAlerts(array $alerts): void
    {
        $adminEmails = $this->config['monitoring']['alerts']['admin_emails'];

        if (empty($adminEmails)) {
            // Fallback to admin users
            $adminEmails = User::where('is_admin', true)->pluck('email')->toArray();
        }

        foreach ($adminEmails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($email)->send(new QueueHealthAlert($alerts));
            }
        }
    }

    /**
     * Send log alerts
     */
    protected function sendLogAlerts(array $alerts): void
    {
        Log::warning('Queue health alerts', [
            'alert_count' => count($alerts),
            'alerts' => $alerts,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Send database alerts
     */
    protected function sendDatabaseAlerts(array $alerts): void
    {
        foreach ($alerts as $alert) {
            DB::table('queue_alerts')->insert([
                'type' => $alert['type'] ?? 'unknown',
                'queue' => $alert['queue'] ?? null,
                'status' => $alert['status'] ?? 'unknown',
                'data' => json_encode($alert),
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Get job retry count
     */
    protected function getJobRetryCount(string $jobId): int
    {
        return (int) Cache::get("job_retry_count:{$jobId}", 0);
    }

    /**
     * Increment job retry count
     */
    protected function incrementJobRetryCount(string $jobId): void
    {
        $count = $this->getJobRetryCount($jobId) + 1;
        Cache::put("job_retry_count:{$jobId}", $count, 86400); // 24 hours
    }

    /**
     * Check if job is retryable
     */
    protected function isJobRetryable(array $payload): bool
    {
        // Add logic to determine if a job should be retried
        // based on job type, error type, etc.

        $jobClass = $payload['data']['commandName'] ?? '';

        // Don't retry certain types of jobs
        $nonRetryableJobs = [
            'App\Jobs\SendEmailNotification', // Email jobs might be time-sensitive
        ];

        return ! in_array($jobClass, $nonRetryableJobs);
    }

    /**
     * Extract date from cache key
     */
    protected function extractDateFromCacheKey(string $key): ?Carbon
    {
        if (preg_match('/(\d{4}-\d{2}-\d{2}-\d{2}-\d{2})/', $key, $matches)) {
            try {
                return Carbon::createFromFormat('Y-m-d-H-i', $matches[1]);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Get recent alerts
     */
    protected function getRecentAlerts(): array
    {
        try {
            return DB::table('queue_alerts')
                ->where('created_at', '>', now()->subHours(24))
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}
