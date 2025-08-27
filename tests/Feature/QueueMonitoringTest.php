<?php

namespace Tests\Feature;

use App\Jobs\SendEmailNotification;
use App\Mail\QueueHealthAlert;
use App\Models\User;
use App\Services\QueueMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class QueueMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected QueueMonitoringService $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monitor = app(QueueMonitoringService::class);

        // Clear any cached data
        Cache::flush();

        // Mock Redis for testing
        Redis::shouldReceive('connection')->andReturnSelf();
        Redis::shouldReceive('ping')->andReturn('PONG');
        Redis::shouldReceive('info')->andReturn([
            'used_memory' => 1000000,
            'maxmemory' => 10000000,
            'connected_clients' => 5,
            'uptime_in_seconds' => 3600,
        ]);
        Redis::shouldReceive('llen')->andReturn(0);
    }

    public function test_can_get_queue_statistics()
    {
        $stats = $this->monitor->getQueueStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('default', $stats);
        $this->assertArrayHasKey('high', $stats);
        $this->assertArrayHasKey('emails', $stats);
        $this->assertArrayHasKey('payments', $stats);

        foreach ($stats as $queueName => $queueStats) {
            $this->assertArrayHasKey('connection', $queueStats);
            $this->assertArrayHasKey('pending', $queueStats);
            $this->assertArrayHasKey('processing', $queueStats);
            $this->assertArrayHasKey('failed', $queueStats);
            $this->assertArrayHasKey('health_status', $queueStats);
        }
    }

    public function test_can_get_pending_jobs_count()
    {
        Queue::fake();

        // Dispatch some jobs
        dispatch(new SendEmailNotification(
            User::factory()->create(),
            'welcome',
            []
        ))->onQueue('emails');

        $count = $this->monitor->getPendingJobsCount('emails');
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_can_get_failed_jobs_count()
    {
        // Create a failed job record
        DB::table('failed_jobs')->insert([
            'uuid' => \Str::uuid(),
            'connection' => 'redis',
            'queue' => 'emails',
            'payload' => json_encode(['test' => 'data']),
            'exception' => 'Test exception',
            'failed_at' => now(),
        ]);

        $count = $this->monitor->getFailedJobsCount('emails');
        $this->assertSame(1, $count);
    }

    public function test_can_check_queue_health_status()
    {
        $health = $this->monitor->getQueueHealthStatus('default');

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('issues', $health);
        $this->assertArrayHasKey('last_check', $health);
        $this->assertContains($health['status'], ['healthy', 'warning', 'critical']);
    }

    public function test_detects_high_pending_jobs_warning()
    {
        // Mock high pending jobs count
        Queue::shouldReceive('connection')->andReturnSelf();
        Queue::shouldReceive('size')->andReturn(1500); // Above warning threshold

        $health = $this->monitor->getQueueHealthStatus('default');

        $this->assertSame('warning', $health['status']);
        $this->assertContains('high_pending_jobs', $health['issues']);
    }

    public function test_detects_critical_failed_jobs()
    {
        // Create many failed job records
        for ($i = 0; $i < 250; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => \Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => json_encode(['test' => 'data']),
                'exception' => 'Test exception',
                'failed_at' => now(),
            ]);
        }

        $health = $this->monitor->getQueueHealthStatus('default');

        $this->assertSame('critical', $health['status']);
        $this->assertContains('critical_failed_jobs', $health['issues']);
    }

    public function test_can_perform_comprehensive_health_check()
    {
        $healthCheck = $this->monitor->performHealthCheck();

        $this->assertIsArray($healthCheck);
        $this->assertArrayHasKey('overall_status', $healthCheck);
        $this->assertArrayHasKey('checked_at', $healthCheck);
        $this->assertArrayHasKey('alerts', $healthCheck);
        $this->assertArrayHasKey('stats', $healthCheck);
        $this->assertArrayHasKey('redis_health', $healthCheck);
        $this->assertArrayHasKey('worker_health', $healthCheck);

        $this->assertContains($healthCheck['overall_status'], ['healthy', 'warning', 'critical']);
    }

    public function test_can_check_redis_health()
    {
        $redisHealth = $this->monitor->checkRedisHealth();

        $this->assertIsArray($redisHealth);
        $this->assertArrayHasKey('healthy', $redisHealth);

        if ($redisHealth['healthy']) {
            $this->assertArrayHasKey('memory_usage', $redisHealth);
            $this->assertArrayHasKey('connected_clients', $redisHealth);
            $this->assertArrayHasKey('uptime', $redisHealth);
        } else {
            $this->assertArrayHasKey('error', $redisHealth);
        }
    }

    public function test_can_check_worker_health()
    {
        $workerHealth = $this->monitor->checkWorkerHealth();

        $this->assertIsArray($workerHealth);
        $this->assertArrayHasKey('healthy', $workerHealth);
        $this->assertArrayHasKey('active_workers', $workerHealth);
        $this->assertArrayHasKey('recommended_workers', $workerHealth);
        $this->assertArrayHasKey('total_pending_jobs', $workerHealth);
    }

    public function test_sends_health_alerts_to_admins()
    {
        Mail::fake();

        // Create admin users
        $admin1 = User::factory()->create(['is_admin' => true]);
        $admin2 = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['is_admin' => false]); // Non-admin

        // Create failed jobs to trigger alerts
        for ($i = 0; $i < 60; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => \Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => json_encode(['test' => 'data']),
                'exception' => 'Test exception',
                'failed_at' => now(),
            ]);
        }

        $this->monitor->performHealthCheck();

        // Verify emails were sent to admins
        Mail::assertQueued(QueueHealthAlert::class, 2);
    }

    public function test_can_get_dashboard_metrics()
    {
        $metrics = $this->monitor->getDashboardMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total_queues', $metrics);
        $this->assertArrayHasKey('healthy_queues', $metrics);
        $this->assertArrayHasKey('total_pending', $metrics);
        $this->assertArrayHasKey('total_processing', $metrics);
        $this->assertArrayHasKey('total_failed', $metrics);
        $this->assertArrayHasKey('health_percentage', $metrics);
        $this->assertArrayHasKey('last_updated', $metrics);

        $this->assertIsInt($metrics['total_queues']);
        $this->assertIsInt($metrics['healthy_queues']);
        $this->assertIsFloat($metrics['health_percentage']);
    }

    public function test_can_clear_failed_jobs()
    {
        // Create failed job records
        DB::table('failed_jobs')->insert([
            [
                'uuid' => \Str::uuid(),
                'connection' => 'redis',
                'queue' => 'emails',
                'payload' => json_encode(['test' => 'data']),
                'exception' => 'Test exception',
                'failed_at' => now(),
            ],
            [
                'uuid' => \Str::uuid(),
                'connection' => 'redis',
                'queue' => 'payments',
                'payload' => json_encode(['test' => 'data']),
                'exception' => 'Test exception',
                'failed_at' => now(),
            ],
        ]);

        // Clear failed jobs for specific queue
        $cleared = $this->monitor->clearFailedJobs('emails');
        $this->assertSame(1, $cleared);

        // Verify only emails queue jobs were cleared
        $this->assertSame(0, $this->monitor->getFailedJobsCount('emails'));
        $this->assertSame(1, $this->monitor->getFailedJobsCount('payments'));

        // Clear all failed jobs
        $cleared = $this->monitor->clearFailedJobs();
        $this->assertSame(1, $cleared);
        $this->assertSame(0, $this->monitor->getFailedJobsCount('payments'));
    }

    public function test_can_scale_workers()
    {
        $scalingActions = $this->monitor->scaleWorkers();

        $this->assertIsArray($scalingActions);

        foreach ($scalingActions as $action) {
            $this->assertArrayHasKey('action', $action);
            $this->assertArrayHasKey('current_workers', $action);
            $this->assertArrayHasKey('target_workers', $action);
            $this->assertContains($action['action'], ['scale_up', 'scale_down']);
        }
    }

    public function test_caches_health_check_results()
    {
        $this->monitor->performHealthCheck();

        $cached = Cache::get('queue_health_check');
        $this->assertNotNull($cached);
        $this->assertArrayHasKey('overall_status', $cached);
        $this->assertArrayHasKey('checked_at', $cached);
    }

    public function test_updates_last_job_time()
    {
        $this->monitor->updateLastJobTime('emails');

        $lastJobTime = $this->monitor->getLastJobTime('emails');
        $this->assertInstanceOf(\Carbon\Carbon::class, $lastJobTime);
        $this->assertTrue($lastJobTime->isToday());
    }

    public function test_detects_stalled_queue()
    {
        // Set last job time to 2 hours ago
        Cache::put('queue_stats:default:last_job', now()->subHours(2)->toISOString(), 3600);

        // Mock pending jobs
        Queue::shouldReceive('connection')->andReturnSelf();
        Queue::shouldReceive('size')->andReturn(10);

        $health = $this->monitor->getQueueHealthStatus('default');

        $this->assertContains('stalled_queue', $health['issues']);
    }

    public function test_handles_redis_connection_failure()
    {
        // Mock Redis connection failure
        Redis::shouldReceive('connection')->andThrow(new \Exception('Connection failed'));

        $redisHealth = $this->monitor->checkRedisHealth();

        $this->assertFalse($redisHealth['healthy']);
        $this->assertArrayHasKey('error', $redisHealth);
        $this->assertSame('Connection failed', $redisHealth['error']);
    }
}
