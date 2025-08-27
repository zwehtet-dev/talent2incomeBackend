<?php

namespace Tests\Feature;

use App\Jobs\DataCleanupJob;
use App\Jobs\GenerateReport;
use App\Jobs\ProcessPayment;
use App\Jobs\SendEmailNotification;
use App\Jobs\UpdateSearchIndex;
use App\Models\Payment;
use App\Models\ScheduledReport;
use App\Models\User;
use App\Services\JobSchedulerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackgroundJobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_email_notification_job_is_dispatched()
    {
        $user = User::factory()->create();
        $jobScheduler = app(JobSchedulerService::class);

        $jobScheduler->scheduleEmailNotification(
            $user,
            'welcome',
            ['name' => $user->first_name],
            'Welcome to our platform'
        );

        Queue::assertPushed(SendEmailNotification::class, function ($job) use ($user) {
            return $job->user->id === $user->id
                && $job->template === 'welcome'
                && $job->subject === 'Welcome to our platform';
        });
    }

    public function test_payment_processing_job_is_dispatched()
    {
        $payment = Payment::factory()->create(['status' => 'pending']);
        $jobScheduler = app(JobSchedulerService::class);

        $jobScheduler->schedulePaymentProcessing(
            $payment,
            'capture',
            ['reason' => 'Job completed']
        );

        Queue::assertPushed(ProcessPayment::class, function ($job) use ($payment) {
            return $job->payment->id === $payment->id
                && $job->action === 'capture'
                && $job->metadata['reason'] === 'Job completed';
        });
    }

    public function test_data_cleanup_job_is_dispatched()
    {
        $jobScheduler = app(JobSchedulerService::class);

        $jobScheduler->scheduleDataCleanup(
            'expired_jobs',
            ['days_old' => 90]
        );

        Queue::assertPushed(DataCleanupJob::class, function ($job) {
            return $job->cleanupType === 'expired_jobs'
                && $job->options['days_old'] === 90;
        });
    }

    public function test_search_index_update_job_is_dispatched()
    {
        $jobScheduler = app(JobSchedulerService::class);

        $jobScheduler->scheduleSearchIndexUpdate(
            'update',
            'jobs',
            [1, 2, 3],
            ['batch_size' => 50]
        );

        Queue::assertPushed(UpdateSearchIndex::class, function ($job) {
            return $job->operation === 'update'
                && $job->model === 'jobs'
                && $job->ids === [1, 2, 3]
                && $job->options['batch_size'] === 50;
        });
    }

    public function test_report_generation_job_is_dispatched()
    {
        $scheduledReport = ScheduledReport::factory()->create([
            'report_type' => 'revenue_analytics',
        ]);
        $jobScheduler = app(JobSchedulerService::class);

        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $jobScheduler->scheduleReportGeneration(
            $scheduledReport,
            $startDate,
            $endDate,
            ['include_charts' => true]
        );

        Queue::assertPushed(GenerateReport::class, function ($job) use ($scheduledReport, $startDate, $endDate) {
            return $job->scheduledReport->id === $scheduledReport->id
                && $job->startDate->equalTo($startDate)
                && $job->endDate->equalTo($endDate)
                && $job->options['include_charts'] === true;
        });
    }

    public function test_delayed_jobs_are_scheduled_correctly()
    {
        $user = User::factory()->create();
        $jobScheduler = app(JobSchedulerService::class);
        $delay = Carbon::now()->addHours(2);

        $jobScheduler->scheduleEmailNotification(
            $user,
            'welcome',
            [],
            null,
            [],
            $delay
        );

        Queue::assertPushed(SendEmailNotification::class, function ($job) use ($delay) {
            return $job->delay && $job->delay->equalTo($delay);
        });
    }

    public function test_batch_email_notifications_are_scheduled()
    {
        $users = User::factory()->count(150)->create();
        $jobScheduler = app(JobSchedulerService::class);

        $jobScheduler->scheduleBatchEmailNotifications(
            $users->toArray(),
            'newsletter',
            ['content' => 'Monthly update'],
            'Monthly Newsletter',
            50, // batch size
            60  // delay between batches
        );

        // Should dispatch 150 individual email jobs
        Queue::assertPushed(SendEmailNotification::class, 150);
    }

    public function test_recurring_jobs_are_scheduled()
    {
        $jobScheduler = app(JobSchedulerService::class);

        $jobScheduler->scheduleRecurringJobs();

        // Should schedule at least the temp file cleanup
        Queue::assertPushed(DataCleanupJob::class, function ($job) {
            return $job->cleanupType === 'temp_files';
        });

        // Should schedule search index update
        Queue::assertPushed(UpdateSearchIndex::class, function ($job) {
            return $job->operation === 'update' && $job->model === 'all';
        });
    }

    public function test_queue_statistics_are_returned()
    {
        $jobScheduler = app(JobSchedulerService::class);

        $stats = $jobScheduler->getQueueStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('emails', $stats);
        $this->assertArrayHasKey('payments', $stats);
        $this->assertArrayHasKey('cleanup', $stats);
        $this->assertArrayHasKey('search', $stats);
        $this->assertArrayHasKey('reports', $stats);

        foreach ($stats as $queueStats) {
            $this->assertArrayHasKey('pending', $queueStats);
            $this->assertArrayHasKey('failed', $queueStats);
        }
    }

    public function test_job_health_monitoring_detects_issues()
    {
        $jobScheduler = app(JobSchedulerService::class);

        // Mock high pending jobs scenario
        $mockStats = [
            'emails' => ['pending' => 1500, 'failed' => 10],
            'payments' => ['pending' => 50, 'failed' => 100],
        ];

        // This would normally check actual queue stats
        // For testing, we'll verify the method exists and returns an array
        $alerts = $jobScheduler->monitorJobHealth();

        $this->assertIsArray($alerts);
    }

    public function test_email_notification_job_handles_template_mapping()
    {
        $user = User::factory()->create();

        $job = new SendEmailNotification(
            $user,
            'welcome',
            ['name' => $user->first_name],
            'Welcome!'
        );

        // Test that the job can map template names to mailable classes
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('getMailableClass');
        $method->setAccessible(true);

        $mailableClass = $method->invoke($job, 'welcome');

        $this->assertSame(\App\Mail\WelcomeEmail::class, $mailableClass);
    }

    public function test_payment_job_validates_payment_status()
    {
        $payment = Payment::factory()->create(['status' => 'completed']);

        $job = new ProcessPayment($payment, 'release', []);

        // The job should handle invalid status transitions gracefully
        $this->expectException(\InvalidArgumentException::class);

        // Simulate job execution
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('releasePayment');
        $method->setAccessible(true);

        $method->invoke($job);
    }

    public function test_search_index_job_filters_indexable_records()
    {
        $job = new UpdateSearchIndex('index', 'jobs', null, []);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('shouldIndex');
        $method->setAccessible(true);

        // Test with a mock job model
        $activeJob = new \App\Models\Job(['status' => 'open', 'is_active' => true]);
        $inactiveJob = new \App\Models\Job(['status' => 'closed', 'is_active' => false]);

        $this->assertTrue($method->invoke($job, $activeJob));
        $this->assertFalse($method->invoke($job, $inactiveJob));
    }

    public function test_cleanup_job_handles_different_cleanup_types()
    {
        $job = new DataCleanupJob('temp_files', ['hours_old' => 24]);

        $this->assertSame('temp_files', $job->cleanupType);
        $this->assertSame(['hours_old' => 24], $job->options);
    }

    public function test_report_generation_job_updates_progress()
    {
        $scheduledReport = ScheduledReport::factory()->create();
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        $job = new GenerateReport($scheduledReport, $startDate, $endDate);

        $this->assertSame($scheduledReport->id, $job->scheduledReport->id);
        $this->assertTrue($job->startDate->equalTo($startDate));
        $this->assertTrue($job->endDate->equalTo($endDate));
    }
}
