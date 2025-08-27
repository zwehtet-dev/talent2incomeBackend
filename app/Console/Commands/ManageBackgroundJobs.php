<?php

namespace App\Console\Commands;

use App\Services\JobSchedulerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ManageBackgroundJobs extends Command
{
    protected $signature = 'jobs:manage 
                           {action : The action to perform (status|retry|clear|health|schedule)}
                           {--queue= : Specific queue to target}
                           {--force : Force the action without confirmation}';

    protected $description = 'Manage background job system operations';

    public function handle(JobSchedulerService $jobScheduler): int
    {
        $action = $this->argument('action');
        $queue = $this->option('queue');

        try {
            switch ($action) {
                case 'status':
                    return $this->showStatus($jobScheduler);
                case 'retry':
                    return $this->retryJobs($jobScheduler, $queue);
                case 'clear':
                    return $this->clearJobs($jobScheduler, $queue);
                case 'health':
                    return $this->checkHealth($jobScheduler);
                case 'schedule':
                    return $this->scheduleRecurring($jobScheduler);
                default:
                    $this->error("Unknown action: {$action}");

                    return 1;
            }
        } catch (\Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");
            Log::error('ManageBackgroundJobs command failed', [
                'action' => $action,
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);

            return 1;
        }
    }

    protected function showStatus(JobSchedulerService $jobScheduler): int
    {
        $this->info('Background Job System Status');
        $this->line('================================');

        $stats = $jobScheduler->getQueueStatistics();

        $headers = ['Queue', 'Pending Jobs', 'Failed Jobs', 'Status'];
        $rows = [];

        foreach ($stats as $queue => $queueStats) {
            $status = 'OK';
            if ($queueStats['pending'] > 1000) {
                $status = 'HIGH LOAD';
            } elseif ($queueStats['failed'] > 50) {
                $status = 'ERRORS';
            }

            $rows[] = [
                $queue,
                number_format($queueStats['pending']),
                number_format($queueStats['failed']),
                $status,
            ];
        }

        $this->table($headers, $rows);

        $totalPending = array_sum(array_column($stats, 'pending'));
        $totalFailed = array_sum(array_column($stats, 'failed'));

        $this->line('');
        $this->info('Total Pending: ' . number_format($totalPending));
        $this->info('Total Failed: ' . number_format($totalFailed));

        return 0;
    }

    protected function retryJobs(JobSchedulerService $jobScheduler, ?string $queue): int
    {
        $target = $queue ?? 'all queues';

        if (! $this->option('force')) {
            if (! $this->confirm("Retry failed jobs for {$target}?")) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        $this->info("Retrying failed jobs for {$target}...");

        $result = $jobScheduler->retryFailedJobs($queue);

        if ($result === 0) {
            $this->info('Failed jobs retry completed successfully.');
        } else {
            $this->error('Failed jobs retry encountered errors.');
        }

        return $result;
    }

    protected function clearJobs(JobSchedulerService $jobScheduler, ?string $queue): int
    {
        $target = $queue ?? 'all queues';

        if (! $this->option('force')) {
            if (! $this->confirm("Clear failed jobs for {$target}? This action cannot be undone.")) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        $this->info("Clearing failed jobs for {$target}...");

        $result = $jobScheduler->clearFailedJobs($queue);

        if ($result === 0) {
            $this->info('Failed jobs cleared successfully.');
        } else {
            $this->error('Failed jobs clear encountered errors.');
        }

        return $result;
    }

    protected function checkHealth(JobSchedulerService $jobScheduler): int
    {
        $this->info('Checking job queue health...');

        $alerts = $jobScheduler->monitorJobHealth();

        if (empty($alerts)) {
            $this->info('✅ All job queues are healthy.');

            return 0;
        }

        $this->warn('⚠️  Job queue health issues detected:');
        $this->line('');

        foreach ($alerts as $alert) {
            $icon = $alert['severity'] === 'error' ? '❌' : '⚠️';
            $this->line("{$icon} {$alert['type']} on queue '{$alert['queue']}': {$alert['count']} items");
        }

        $this->line('');
        $this->info('Administrators have been notified of these issues.');

        return count($alerts);
    }

    protected function scheduleRecurring(JobSchedulerService $jobScheduler): int
    {
        $this->info('Scheduling recurring background jobs...');

        $jobScheduler->scheduleRecurringJobs();

        $this->info('✅ Recurring jobs scheduled successfully.');

        return 0;
    }
}
