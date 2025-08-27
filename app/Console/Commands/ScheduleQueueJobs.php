<?php

namespace App\Console\Commands;

use App\Jobs\DataCleanupJob;
use App\Jobs\ProcessDailyAnalytics;
use App\Jobs\ProcessSavedSearchNotifications;
use App\Jobs\UpdateSearchIndex;
use App\Jobs\UpdateUserRatingCache;
use App\Services\JobSchedulerService;
use App\Services\QueueMonitoringService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScheduleQueueJobs extends Command
{
    protected $signature = 'queue:schedule 
                           {--job=* : Specific jobs to schedule}
                           {--force : Force scheduling even if already scheduled}
                           {--dry-run : Show what would be scheduled without executing}';

    protected $description = 'Schedule recurring queue jobs based on cron configuration';

    protected array $scheduledJobs = [
        'health_check' => [
            'interval' => '*/5', // Every 5 minutes
            'description' => 'Queue health monitoring',
            'job_class' => null, // Handled by monitoring service
        ],
        'daily_cleanup' => [
            'time' => '02:00',
            'description' => 'Daily data cleanup',
            'job_class' => DataCleanupJob::class,
        ],
        'weekly_cleanup' => [
            'day' => 'sunday',
            'time' => '03:00',
            'description' => 'Weekly comprehensive cleanup',
            'job_class' => DataCleanupJob::class,
        ],
        'search_maintenance' => [
            'time' => '01:00',
            'description' => 'Daily search index maintenance',
            'job_class' => UpdateSearchIndex::class,
        ],
        'search_rebuild' => [
            'day' => 1, // First day of month
            'time' => '04:00',
            'description' => 'Monthly search index rebuild',
            'job_class' => UpdateSearchIndex::class,
        ],
        'rating_cache_update' => [
            'time' => '00:30',
            'description' => 'Daily rating cache update',
            'job_class' => UpdateUserRatingCache::class,
        ],
        'daily_analytics' => [
            'time' => '05:00',
            'description' => 'Daily analytics processing',
            'job_class' => ProcessDailyAnalytics::class,
        ],
        'saved_search_notifications' => [
            'interval' => '0 */6', // Every 6 hours
            'description' => 'Process saved search notifications',
            'job_class' => ProcessSavedSearchNotifications::class,
        ],
    ];

    public function handle(
        JobSchedulerService $jobScheduler,
        QueueMonitoringService $monitor
    ): int {
        $specificJobs = $this->option('job');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN - No jobs will be actually scheduled');
            $this->line('');
        }

        try {
            $scheduledCount = 0;
            $skippedCount = 0;

            foreach ($this->scheduledJobs as $jobName => $config) {
                // Skip if specific jobs requested and this isn't one of them
                if (! empty($specificJobs) && ! in_array($jobName, $specificJobs)) {
                    continue;
                }

                $shouldSchedule = $this->shouldScheduleJob($jobName, $config, $force);

                if (! $shouldSchedule) {
                    $this->line("⏭️  Skipping {$jobName} - not due or already scheduled");
                    $skippedCount++;

                    continue;
                }

                if ($dryRun) {
                    $this->info("Would schedule: {$jobName} - {$config['description']}");
                    $scheduledCount++;

                    continue;
                }

                $success = $this->scheduleJob($jobName, $config, $jobScheduler, $monitor);

                if ($success) {
                    $this->info("✅ Scheduled: {$jobName} - {$config['description']}");
                    $scheduledCount++;
                } else {
                    $this->error("❌ Failed to schedule: {$jobName}");
                }
            }

            $this->line('');
            $this->info("Scheduling complete: {$scheduledCount} scheduled, {$skippedCount} skipped");

            Log::info('Queue job scheduling completed', [
                'scheduled_count' => $scheduledCount,
                'skipped_count' => $skippedCount,
                'dry_run' => $dryRun,
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("Scheduling failed: {$e->getMessage()}");
            Log::error('Queue job scheduling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    protected function shouldScheduleJob(string $jobName, array $config, bool $force): bool
    {
        if ($force) {
            return true;
        }

        $now = now();
        $cacheKey = "scheduled_job:{$jobName}:last_run";
        $lastRun = cache($cacheKey);

        // Handle interval-based jobs (like health checks)
        if (isset($config['interval'])) {
            if (! $lastRun) {
                return true;
            }

            $lastRunTime = Carbon::parse($lastRun);
            $intervalMinutes = $this->parseInterval($config['interval']);

            return $lastRunTime->addMinutes($intervalMinutes)->isPast();
        }

        // Handle time-based jobs
        if (isset($config['time'])) {
            $targetTime = Carbon::parse($config['time']);

            // Handle daily jobs
            if (! isset($config['day'])) {
                if (! $lastRun) {
                    return $now->format('H:i') >= $targetTime->format('H:i');
                }

                $lastRunDate = Carbon::parse($lastRun)->startOfDay();

                return $lastRunDate->lt($now->startOfDay()) &&
                       $now->format('H:i') >= $targetTime->format('H:i');
            }

            // Handle weekly jobs
            if (is_string($config['day'])) {
                $targetDay = strtolower($config['day']);
                $currentDay = strtolower($now->format('l'));

                if ($currentDay !== $targetDay) {
                    return false;
                }

                if (! $lastRun) {
                    return $now->format('H:i') >= $targetTime->format('H:i');
                }

                $lastRunWeek = Carbon::parse($lastRun)->startOfWeek();

                return $lastRunWeek->lt($now->startOfWeek()) &&
                       $now->format('H:i') >= $targetTime->format('H:i');
            }

            // Handle monthly jobs
            if (is_int($config['day'])) {
                if ($now->day !== $config['day']) {
                    return false;
                }

                if (! $lastRun) {
                    return $now->format('H:i') >= $targetTime->format('H:i');
                }

                $lastRunMonth = Carbon::parse($lastRun)->startOfMonth();

                return $lastRunMonth->lt($now->startOfMonth()) &&
                       $now->format('H:i') >= $targetTime->format('H:i');
            }
        }

        return false;
    }

    protected function parseInterval(string $interval): int
    {
        // Parse cron-style intervals to minutes
        if (str_starts_with($interval, '*/')) {
            return (int) substr($interval, 2);
        }

        if (str_contains($interval, '*/')) {
            // Handle "0 */6" format (every 6 hours)
            $parts = explode(' ', $interval);
            if (count($parts) === 2 && str_starts_with($parts[1], '*/')) {
                return (int) substr($parts[1], 2) * 60;
            }
        }

        return 5; // Default to 5 minutes
    }

    protected function scheduleJob(
        string $jobName,
        array $config,
        JobSchedulerService $jobScheduler,
        QueueMonitoringService $monitor
    ): bool {
        try {
            switch ($jobName) {
                case 'health_check':
                    $monitor->performHealthCheck();

                    break;

                case 'daily_cleanup':
                    $jobScheduler->scheduleDataCleanup('daily', [
                        'temp_files' => ['hours_old' => 24],
                        'logs' => ['days_old' => 30],
                    ]);

                    break;

                case 'weekly_cleanup':
                    $jobScheduler->scheduleDataCleanup('weekly', [
                        'expired_jobs' => ['days_old' => 90],
                        'old_messages' => ['days_old' => 180],
                        'inactive_users' => ['days_inactive' => 730],
                    ]);

                    break;

                case 'search_maintenance':
                    $jobScheduler->scheduleSearchIndexUpdate(
                        'update',
                        'all',
                        null,
                        ['since' => now()->subDay()]
                    );

                    break;

                case 'search_rebuild':
                    $jobScheduler->scheduleSearchIndexUpdate(
                        'rebuild',
                        'all',
                        null,
                        ['full_rebuild' => true]
                    );

                    break;

                case 'rating_cache_update':
                    dispatch(new UpdateUserRatingCache())->onQueue('analytics');

                    break;

                case 'daily_analytics':
                    dispatch(new ProcessDailyAnalytics(now()->subDay()))->onQueue('analytics');

                    break;

                case 'saved_search_notifications':
                    dispatch(new ProcessSavedSearchNotifications())->onQueue('emails');

                    break;

                default:
                    throw new \InvalidArgumentException("Unknown job: {$jobName}");
            }

            // Mark job as scheduled
            $cacheKey = "scheduled_job:{$jobName}:last_run";
            cache([$cacheKey => now()->toISOString()], 86400); // Cache for 24 hours

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to schedule job: {$jobName}", [
                'error' => $e->getMessage(),
                'config' => $config,
            ]);

            return false;
        }
    }

    protected function displayScheduleStatus(): void
    {
        $this->info('Scheduled Jobs Status:');
        $this->line('======================');

        $headers = ['Job', 'Schedule', 'Last Run', 'Next Due', 'Status'];
        $rows = [];

        foreach ($this->scheduledJobs as $jobName => $config) {
            $cacheKey = "scheduled_job:{$jobName}:last_run";
            $lastRun = cache($cacheKey);

            $schedule = $this->formatSchedule($config);
            $lastRunFormatted = $lastRun ? Carbon::parse($lastRun)->diffForHumans() : 'Never';
            $nextDue = $this->calculateNextDue($config, $lastRun);
            $status = $this->shouldScheduleJob($jobName, $config, false) ? '🟡 Due' : '✅ OK';

            $rows[] = [
                $jobName,
                $schedule,
                $lastRunFormatted,
                $nextDue,
                $status,
            ];
        }

        $this->table($headers, $rows);
    }

    protected function formatSchedule(array $config): string
    {
        if (isset($config['interval'])) {
            return "Every {$config['interval']} minutes";
        }

        if (isset($config['time'])) {
            $time = $config['time'];

            if (! isset($config['day'])) {
                return "Daily at {$time}";
            }

            if (is_string($config['day'])) {
                return "Weekly on {$config['day']} at {$time}";
            }

            if (is_int($config['day'])) {
                return "Monthly on day {$config['day']} at {$time}";
            }
        }

        return 'Unknown';
    }

    protected function calculateNextDue(array $config, ?string $lastRun): string
    {
        $now = now();

        if (isset($config['interval'])) {
            if (! $lastRun) {
                return 'Now';
            }

            $lastRunTime = Carbon::parse($lastRun);
            $intervalMinutes = $this->parseInterval($config['interval']);
            $nextDue = $lastRunTime->addMinutes($intervalMinutes);

            return $nextDue->isPast() ? 'Now' : $nextDue->diffForHumans();
        }

        if (isset($config['time'])) {
            $targetTime = Carbon::parse($config['time']);

            if (! isset($config['day'])) {
                // Daily job
                $nextRun = $now->copy()->setTimeFromTimeString($config['time']);
                if ($nextRun->isPast()) {
                    $nextRun->addDay();
                }

                return $nextRun->diffForHumans();
            }

            // Weekly or monthly jobs would need more complex calculation
            return 'Calculated based on schedule';
        }

        return 'Unknown';
    }
}
