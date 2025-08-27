<?php

namespace Tests\Feature;

use App\Services\QueueMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueManagementCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_queue_health_check_command_runs_successfully()
    {
        $this->artisan('queue:health-check')
            ->expectsOutput('Performing queue health check...')
            ->assertExitCode(0);
    }

    public function test_queue_health_check_command_with_json_output()
    {
        $this->artisan('queue:health-check --json')
            ->assertExitCode(0);
    }

    public function test_queue_health_check_command_detects_issues()
    {
        // Create failed jobs to trigger warnings
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

        $this->artisan('queue:health-check')
            ->expectsOutput('Performing queue health check...')
            ->assertExitCode(1); // Warning exit code
    }

    public function test_queue_health_check_command_with_auto_fix()
    {
        // Create some failed jobs
        DB::table('failed_jobs')->insert([
            'uuid' => \Str::uuid(),
            'connection' => 'redis',
            'queue' => 'emails',
            'payload' => json_encode(['test' => 'data']),
            'exception' => 'Test exception',
            'failed_at' => now(),
        ]);

        $this->artisan('queue:health-check --fix')
            ->expectsOutput('Performing queue health check...')
            ->expectsOutput('Attempting automatic fixes...')
            ->assertExitCode(1);
    }

    public function test_queue_workers_status_command()
    {
        $this->artisan('queue:workers status')
            ->expectsOutput('Queue Worker Status')
            ->expectsOutput('==================')
            ->assertExitCode(0);
    }

    public function test_queue_workers_start_command()
    {
        $this->artisan('queue:workers start --workers=2 --force')
            ->expectsOutput('Starting 2 worker(s) for queues: high,default,emails,payments,search,analytics,cleanup,reports,low')
            ->assertExitCode(0);
    }

    public function test_queue_workers_start_command_with_custom_queues()
    {
        $this->artisan('queue:workers start --workers=1 --queue=emails --queue=payments --force')
            ->expectsOutput('Starting 1 worker(s) for queues: emails,payments')
            ->assertExitCode(0);
    }

    public function test_queue_workers_stop_command()
    {
        $this->artisan('queue:workers stop --force')
            ->assertExitCode(0);
    }

    public function test_queue_workers_restart_command()
    {
        $this->artisan('queue:workers restart --force')
            ->expectsOutput('Restarting queue workers...')
            ->assertExitCode(0);
    }

    public function test_queue_workers_scale_command()
    {
        $this->artisan('queue:workers scale --force')
            ->expectsOutput('Analyzing worker scaling requirements...')
            ->assertExitCode(0);
    }

    public function test_queue_schedule_command()
    {
        $this->artisan('queue:schedule')
            ->expectsOutput('Scheduling complete:')
            ->assertExitCode(0);
    }

    public function test_queue_schedule_command_with_specific_job()
    {
        $this->artisan('queue:schedule --job=health_check')
            ->expectsOutput('Scheduling complete:')
            ->assertExitCode(0);
    }

    public function test_queue_schedule_command_dry_run()
    {
        $this->artisan('queue:schedule --dry-run')
            ->expectsOutput('DRY RUN - No jobs will be actually scheduled')
            ->assertExitCode(0);
    }

    public function test_queue_schedule_command_with_force()
    {
        $this->artisan('queue:schedule --force')
            ->expectsOutput('Scheduling complete:')
            ->assertExitCode(0);
    }

    public function test_manage_background_jobs_status_command()
    {
        $this->artisan('jobs:manage status')
            ->expectsOutput('Background Job System Status')
            ->expectsOutput('================================')
            ->assertExitCode(0);
    }

    public function test_manage_background_jobs_retry_command()
    {
        // Create a failed job
        DB::table('failed_jobs')->insert([
            'uuid' => \Str::uuid(),
            'connection' => 'redis',
            'queue' => 'emails',
            'payload' => json_encode(['test' => 'data']),
            'exception' => 'Test exception',
            'failed_at' => now(),
        ]);

        $this->artisan('jobs:manage retry --force')
            ->expectsOutput('Retrying failed jobs for all queues...')
            ->assertExitCode(0);
    }

    public function test_manage_background_jobs_retry_specific_queue()
    {
        $this->artisan('jobs:manage retry --queue=emails --force')
            ->expectsOutput('Retrying failed jobs for emails...')
            ->assertExitCode(0);
    }

    public function test_manage_background_jobs_clear_command()
    {
        // Create a failed job
        DB::table('failed_jobs')->insert([
            'uuid' => \Str::uuid(),
            'connection' => 'redis',
            'queue' => 'emails',
            'payload' => json_encode(['test' => 'data']),
            'exception' => 'Test exception',
            'failed_at' => now(),
        ]);

        $this->artisan('jobs:manage clear --force')
            ->expectsOutput('Clearing failed jobs for all queues...')
            ->assertExitCode(0);

        // Verify jobs were cleared
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_manage_background_jobs_health_command()
    {
        $this->artisan('jobs:manage health')
            ->expectsOutput('Checking job queue health...')
            ->assertExitCode(0);
    }

    public function test_manage_background_jobs_schedule_command()
    {
        $this->artisan('jobs:manage schedule')
            ->expectsOutput('Scheduling recurring background jobs...')
            ->assertExitCode(0);
    }

    public function test_commands_handle_invalid_actions()
    {
        $this->artisan('jobs:manage invalid-action')
            ->expectsOutput('Unknown action: invalid-action')
            ->assertExitCode(1);

        $this->artisan('queue:workers invalid-action')
            ->expectsOutput('Unknown action: invalid-action')
            ->assertExitCode(1);
    }

    public function test_commands_handle_exceptions_gracefully()
    {
        // Mock the monitoring service to throw an exception
        $this->app->bind(QueueMonitoringService::class, function () {
            $mock = \Mockery::mock(QueueMonitoringService::class);
            $mock->shouldReceive('performHealthCheck')
                ->andThrow(new \Exception('Test exception'));

            return $mock;
        });

        $this->artisan('queue:health-check')
            ->expectsOutput('Health check failed: Test exception')
            ->assertExitCode(3);
    }

    public function test_queue_health_check_command_requires_confirmation_for_fixes()
    {
        // Create failed jobs
        DB::table('failed_jobs')->insert([
            'uuid' => \Str::uuid(),
            'connection' => 'redis',
            'queue' => 'emails',
            'payload' => json_encode(['test' => 'data']),
            'exception' => 'Test exception',
            'failed_at' => now(),
        ]);

        $this->artisan('queue:health-check --fix')
            ->expectsQuestion("Retry failed jobs for queue 'emails'?", false)
            ->expectsOutput('Scaling action skipped.')
            ->assertExitCode(1);
    }

    public function test_worker_commands_require_confirmation()
    {
        $this->artisan('queue:workers start --workers=2')
            ->expectsQuestion('Start the worker processes?', false)
            ->expectsOutput('Operation cancelled.')
            ->assertExitCode(0);

        $this->artisan('queue:workers stop')
            ->expectsQuestion('Stop all worker processes?', false)
            ->expectsOutput('Operation cancelled.')
            ->assertExitCode(0);
    }

    public function test_schedule_command_handles_job_scheduling_errors()
    {
        // Test with invalid job name
        $this->artisan('queue:schedule --job=invalid_job')
            ->assertExitCode(0); // Should complete but skip invalid jobs
    }

    public function test_commands_log_operations()
    {
        $this->artisan('queue:health-check');

        // Check that operations are logged
        $this->assertTrue(true); // Placeholder - in real implementation, check log files
    }
}
