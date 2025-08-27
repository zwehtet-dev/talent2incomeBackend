<?php

namespace App\Services;

use App\Jobs\DataCleanupJob;
use App\Jobs\GenerateReport;
use App\Jobs\ProcessPayment;
use App\Jobs\SendEmailNotification;
use App\Jobs\UpdateSearchIndex;
use App\Models\Payment;
use App\Models\ScheduledReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class JobSchedulerService
{
    /**
     * Schedule email notification
     * @param array<string, mixed> $data
     * @param array<string> $attachments
     */
    public function scheduleEmailNotification(
        User $user,
        string $template,
        array $data = [],
        ?string $subject = null,
        array $attachments = [],
        ?Carbon $delay = null
    ): void {
        $job = new SendEmailNotification($user, $template, $data, $subject, $attachments);

        if ($delay) {
            $job->delay($delay);
        }

        dispatch($job);

        Log::info('Email notification scheduled', [
            'user_id' => $user->id,
            'template' => $template,
            'delay' => $delay?->toDateTimeString(),
        ]);
    }

    /**
     * Schedule payment processing
     * @param array<string, mixed> $metadata
     */
    public function schedulePaymentProcessing(
        Payment $payment,
        string $action,
        array $metadata = [],
        ?Carbon $delay = null
    ): void {
        $job = new ProcessPayment($payment, $action, $metadata);

        if ($delay) {
            $job->delay($delay);
        }

        dispatch($job);

        Log::info('Payment processing scheduled', [
            'payment_id' => $payment->id,
            'action' => $action,
            'delay' => $delay?->toDateTimeString(),
        ]);
    }

    /**
     * Schedule data cleanup
     * @param array<string, mixed> $options
     */
    public function scheduleDataCleanup(
        string $cleanupType,
        array $options = [],
        ?Carbon $delay = null
    ): void {
        $job = new DataCleanupJob($cleanupType, $options);

        if ($delay) {
            $job->delay($delay);
        }

        dispatch($job);

        Log::info('Data cleanup scheduled', [
            'type' => $cleanupType,
            'options' => $options,
            'delay' => $delay?->toDateTimeString(),
        ]);
    }

    /**
     * Schedule search index update
     * @param array<int>|null $ids
     * @param array<string, mixed> $options
     */
    public function scheduleSearchIndexUpdate(
        string $operation,
        string $model,
        ?array $ids = null,
        array $options = [],
        ?Carbon $delay = null
    ): void {
        $job = new UpdateSearchIndex($operation, $model, $ids, $options);

        if ($delay) {
            $job->delay($delay);
        }

        dispatch($job);

        Log::info('Search index update scheduled', [
            'operation' => $operation,
            'model' => $model,
            'ids_count' => $ids ? count($ids) : 'all',
            'delay' => $delay?->toDateTimeString(),
        ]);
    }

    /**
     * Schedule report generation
     * @param array<string, mixed> $options
     */
    public function scheduleReportGeneration(
        ScheduledReport $scheduledReport,
        Carbon $startDate,
        Carbon $endDate,
        array $options = [],
        ?Carbon $delay = null
    ): void {
        $job = new GenerateReport($scheduledReport, $startDate, $endDate, $options);

        if ($delay) {
            $job->delay($delay);
        }

        dispatch($job);

        Log::info('Report generation scheduled', [
            'scheduled_report_id' => $scheduledReport->id,
            'report_type' => $scheduledReport->report_type,
            'period' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
            'delay' => $delay?->toDateTimeString(),
        ]);
    }

    /**
     * Schedule recurring jobs (called by scheduler)
     */
    public function scheduleRecurringJobs(): void
    {
        // Daily cleanup at 2 AM
        $this->scheduleDataCleanup('temp_files', ['hours_old' => 24], now()->setTime(2, 0));

        // Weekly comprehensive cleanup on Sundays at 3 AM
        if (now()->isSunday()) {
            $this->scheduleDataCleanup('full_cleanup', [], now()->setTime(3, 0));
        }

        // Daily search index maintenance at 1 AM
        $this->scheduleSearchIndexUpdate('update', 'all', null, ['since' => now()->subDay()], now()->setTime(1, 0));

        // Monthly search index rebuild on first day at 4 AM
        if (now()->day === 1) {
            $this->scheduleSearchIndexUpdate('rebuild', 'all', null, [], now()->setTime(4, 0));
        }

        Log::info('Recurring jobs scheduled');
    }

    /**
     * Get job queue statistics
     * @return array<string, array<string, int>>
     */
    public function getQueueStatistics(): array
    {
        $queues = ['default', 'emails', 'payments', 'cleanup', 'search', 'reports', 'analytics'];
        $stats = [];

        foreach ($queues as $queue) {
            $stats[$queue] = [
                'pending' => Queue::size($queue),
                'failed' => $this->getFailedJobsCount($queue),
            ];
        }

        return $stats;
    }

    /**
     * Retry failed jobs for a specific queue
     */
    public function retryFailedJobs(string $queue = null): int
    {
        $command = 'php artisan queue:retry';

        if ($queue) {
            $command .= " --queue={$queue}";
        } else {
            $command .= ' all';
        }

        exec($command, $output, $returnCode);

        Log::info('Failed jobs retry initiated', [
            'queue' => $queue ?? 'all',
            'return_code' => $returnCode,
        ]);

        return $returnCode;
    }

    /**
     * Clear failed jobs for a specific queue
     */
    public function clearFailedJobs(string $queue = null): int
    {
        $command = 'php artisan queue:flush';

        if ($queue) {
            // Laravel doesn't support queue-specific flush, so we'll use a workaround
            $command = "php artisan queue:failed | grep '{$queue}' | awk '{print $2}' | xargs -I {} php artisan queue:forget {}";
        }

        exec($command, $output, $returnCode);

        Log::info('Failed jobs cleared', [
            'queue' => $queue ?? 'all',
            'return_code' => $returnCode,
        ]);

        return $returnCode;
    }

    /**
     * Schedule batch email notifications
     * @param array<User> $users
     * @param array<string, mixed> $data
     */
    public function scheduleBatchEmailNotifications(
        array $users,
        string $template,
        array $data = [],
        ?string $subject = null,
        int $batchSize = 50,
        int $delayBetweenBatches = 60
    ): void {
        $batches = array_chunk($users, max(1, $batchSize));
        $delay = 0;

        foreach ($batches as $batch) {
            foreach ($batch as $user) {
                $this->scheduleEmailNotification(
                    $user,
                    $template,
                    $data,
                    $subject,
                    [],
                    now()->addSeconds($delay)
                );
            }

            $delay += $delayBetweenBatches;
        }

        Log::info('Batch email notifications scheduled', [
            'total_users' => count($users),
            'batches' => count($batches),
            'template' => $template,
        ]);
    }

    /**
     * Monitor job health and send alerts
     * @return array<array<string, mixed>>
     */
    public function monitorJobHealth(): array
    {
        $stats = $this->getQueueStatistics();
        $alerts = [];

        foreach ($stats as $queue => $queueStats) {
            // Alert if too many pending jobs
            if ($queueStats['pending'] > 1000) {
                $alerts[] = [
                    'type' => 'high_pending_jobs',
                    'queue' => $queue,
                    'count' => $queueStats['pending'],
                    'severity' => 'warning',
                ];
            }

            // Alert if too many failed jobs
            if ($queueStats['failed'] > 50) {
                $alerts[] = [
                    'type' => 'high_failed_jobs',
                    'queue' => $queue,
                    'count' => $queueStats['failed'],
                    'severity' => 'error',
                ];
            }
        }

        if (! empty($alerts)) {
            Log::warning('Job queue health alerts', ['alerts' => $alerts]);

            // Notify administrators
            $admins = User::where('is_admin', true)->get();
            foreach ($admins as $admin) {
                $this->scheduleEmailNotification(
                    $admin,
                    'queue_health_alert',
                    ['alerts' => $alerts],
                    'Queue Health Alert - ' . config('app.name')
                );
            }
        }

        return $alerts;
    }

    /**
     * Get failed jobs count for a queue
     */
    protected function getFailedJobsCount(string $queue): int
    {
        // This would typically query the failed_jobs table
        // For now, return 0 as a placeholder
        return 0;
    }
}
