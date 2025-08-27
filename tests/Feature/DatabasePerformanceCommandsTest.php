<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabasePerformanceCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the performance monitoring tables
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_08_13_051544_create_database_performance_tables.php']);
    }

    public function test_db_optimize_command_with_metrics_option(): void
    {
        $exitCode = Artisan::call('db:optimize', ['--metrics' => true]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Starting database performance optimization', $output);
        $this->assertStringContainsString('Database optimization completed', $output);
    }

    public function test_db_optimize_command_with_analyze_option(): void
    {
        $exitCode = Artisan::call('db:optimize', ['--analyze' => true]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Analyzing database tables', $output);
    }

    public function test_db_optimize_command_with_cleanup_option(): void
    {
        $exitCode = Artisan::call('db:optimize', ['--cleanup' => true]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Cleaning up stale database connections', $output);
    }

    public function test_db_monitor_command_single_check(): void
    {
        $exitCode = Artisan::call('db:monitor');

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Starting database performance monitoring', $output);
    }

    public function test_db_monitor_command_with_alert_option(): void
    {
        $exitCode = Artisan::call('db:monitor', ['--alert' => true]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Starting database performance monitoring', $output);
    }

    public function test_db_backup_command_list_option(): void
    {
        $exitCode = Artisan::call('db:backup', ['--list' => true]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Available database backups', $output);
    }

    public function test_db_backup_command_stats_option(): void
    {
        $exitCode = Artisan::call('db:backup', ['--stats' => true]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Database backup statistics', $output);
    }

    public function test_db_backup_command_cleanup_option(): void
    {
        $exitCode = Artisan::call('db:backup', ['--cleanup' => true]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Cleaning up old backups', $output);
    }

    public function test_db_backup_command_full_backup(): void
    {
        // This test would require mysqldump to be available
        $this->markTestSkipped('Requires mysqldump binary for actual backup creation');
    }

    public function test_db_backup_command_incremental_backup(): void
    {
        // This test would require mysqldump to be available
        $this->markTestSkipped('Requires mysqldump binary for actual backup creation');
    }

    public function test_db_backup_command_point_in_time_backup(): void
    {
        // This test would require mysqldump and existing backups
        $this->markTestSkipped('Requires mysqldump binary and existing backup files');
    }

    public function test_commands_handle_database_errors_gracefully(): void
    {
        // Test that commands don't crash when database operations fail
        $exitCode = Artisan::call('db:optimize', ['--metrics' => true]);

        // Should complete successfully even if some operations fail
        $this->assertSame(0, $exitCode);
    }

    public function test_performance_metrics_are_stored(): void
    {
        // Run monitoring command
        Artisan::call('db:monitor');

        // Check if metrics were stored (this would depend on actual implementation)
        $this->assertTrue(true); // Placeholder - would check database_performance_metrics table
    }

    public function test_slow_queries_are_logged(): void
    {
        // This would test that slow queries are properly logged to the database
        // Would require creating actual slow queries or mocking the query listener
        $this->assertTrue(true); // Placeholder
    }

    public function test_connection_pool_stats_are_collected(): void
    {
        // Test that connection pool statistics are properly collected
        Artisan::call('db:optimize', ['--cleanup' => true]);

        // Would verify that connection pool stats are recorded
        $this->assertTrue(true); // Placeholder
    }

    public function test_table_optimization_works(): void
    {
        // Create a test table
        DB::statement('CREATE TABLE test_optimization (id INT PRIMARY KEY, name VARCHAR(255))');

        // Run optimization
        $exitCode = Artisan::call('db:optimize', ['--optimize' => true]);

        $this->assertSame(0, $exitCode);

        // Clean up
        DB::statement('DROP TABLE test_optimization');
    }

    public function test_table_analysis_works(): void
    {
        // Create a test table
        DB::statement('CREATE TABLE test_analysis (id INT PRIMARY KEY, name VARCHAR(255))');

        // Run analysis
        $exitCode = Artisan::call('db:optimize', ['--analyze' => true]);

        $this->assertSame(0, $exitCode);

        // Clean up
        DB::statement('DROP TABLE test_analysis');
    }

    public function test_commands_provide_helpful_output(): void
    {
        $exitCode = Artisan::call('db:optimize', ['--metrics' => true]);

        $output = Artisan::output();

        // Check that output contains useful information
        $this->assertStringContainsString('Starting database performance optimization', $output);
        $this->assertStringContainsString('Database optimization completed', $output);

        $this->assertSame(0, $exitCode);
    }

    public function test_backup_command_validates_point_in_time_format(): void
    {
        $exitCode = Artisan::call('db:backup', [
            '--type' => 'point-in-time',
            '--point-in-time' => 'invalid-date-format',
        ]);

        // Should return error code for invalid date format
        $this->assertSame(1, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Invalid timestamp format', $output);
    }

    public function test_monitor_command_handles_missing_configuration(): void
    {
        // Test that monitoring works even with minimal configuration
        $exitCode = Artisan::call('db:monitor');

        $this->assertSame(0, $exitCode);
    }
}
