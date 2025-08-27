<?php

namespace App\Providers;

use App\Services\DatabaseBackupService;
use App\Services\DatabaseConnectionPoolService;
use App\Services\DatabasePerformanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class DatabasePerformanceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DatabasePerformanceService::class, function ($app) {
            return new DatabasePerformanceService();
        });

        $this->app->singleton(DatabaseConnectionPoolService::class, function ($app) {
            return new DatabaseConnectionPoolService();
        });

        $this->app->singleton(DatabaseBackupService::class, function ($app) {
            return new DatabaseBackupService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (config('database_performance.monitoring.enabled', true)) {
            $this->enableQueryMonitoring();
        }

        if (config('database_performance.connection_pool.enabled', true)) {
            $this->enableConnectionPooling();
        }

        $this->registerScheduledTasks();
    }

    /**
     * Enable database query monitoring
     */
    private function enableQueryMonitoring(): void
    {
        $performanceService = $this->app->make(DatabasePerformanceService::class);

        DB::listen(function ($query) use ($performanceService) {
            // Log slow queries to database
            $this->logSlowQueryToDatabase($query);
        });
    }

    /**
     * Enable connection pooling
     */
    private function enableConnectionPooling(): void
    {
        // Connection pooling is handled by the service itself
        // This method can be used for additional setup if needed
    }

    /**
     * Register scheduled tasks
     */
    private function registerScheduledTasks(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);

            // Schedule database optimization
            $schedule->command('db:optimize --analyze')
                ->daily()
                ->at('02:00')
                ->withoutOverlapping()
                ->runInBackground();

            $schedule->command('db:optimize --optimize')
                ->weekly()
                ->sundays()
                ->at('03:00')
                ->withoutOverlapping()
                ->runInBackground();

            // Schedule performance monitoring
            $schedule->command('db:monitor --alert')
                ->everyFiveMinutes()
                ->withoutOverlapping()
                ->runInBackground();

            // Schedule backups
            $schedule->command('db:backup --type=full')
                ->daily()
                ->at('01:00')
                ->withoutOverlapping()
                ->runInBackground();

            $schedule->command('db:backup --type=incremental')
                ->hourly()
                ->withoutOverlapping()
                ->runInBackground();

            // Schedule cleanup tasks
            $schedule->call(function () {
                $this->cleanupOldMetrics();
            })->daily()->at('04:00');

            $schedule->call(function () {
                $poolService = $this->app->make(DatabaseConnectionPoolService::class);
                $poolService->cleanupStaleConnections();
            })->everyTenMinutes();
        });
    }

    /**
     * Log slow query to database
     * @param mixed $query
     */
    private function logSlowQueryToDatabase($query): void
    {
        $thresholds = config('database_performance.monitoring.slow_query_thresholds', []);
        $sql = $query->sql;
        $time = $query->time;
        $queryType = $this->getQueryType($sql);
        $threshold = $thresholds[$queryType] ?? 1000;

        if ($time >= $threshold) {
            try {
                DB::table('slow_query_logs')->insert([
                    'sql' => $sql,
                    'bindings' => json_encode($query->bindings),
                    'execution_time' => $time,
                    'query_type' => $queryType,
                    'executed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to log slow query to database', [
                    'error' => $e->getMessage(),
                    'sql' => $sql,
                ]);
            }
        }
    }

    /**
     * Get query type from SQL
     */
    private function getQueryType(string $sql): string
    {
        $sql = strtolower(trim($sql));

        if (str_starts_with($sql, 'select')) {
            return 'select';
        }
        if (str_starts_with($sql, 'insert')) {
            return 'insert';
        }
        if (str_starts_with($sql, 'update')) {
            return 'update';
        }
        if (str_starts_with($sql, 'delete')) {
            return 'delete';
        }

        return 'other';
    }

    /**
     * Clean up old performance metrics
     */
    private function cleanupOldMetrics(): void
    {
        try {
            $metricsRetentionDays = config('database_performance.monitoring.metrics_retention_days', 30);
            $slowQueryRetentionDays = config('database_performance.monitoring.slow_query_retention_days', 7);

            // Clean up old performance metrics
            DB::table('database_performance_metrics')
                ->where('created_at', '<', now()->subDays($metricsRetentionDays))
                ->delete();

            // Clean up old slow query logs
            DB::table('slow_query_logs')
                ->where('created_at', '<', now()->subDays($slowQueryRetentionDays))
                ->delete();

            // Clean up old connection pool stats
            DB::table('connection_pool_stats')
                ->where('created_at', '<', now()->subDays($metricsRetentionDays))
                ->delete();

            // Clean up old table statistics
            DB::table('table_statistics')
                ->where('created_at', '<', now()->subDays($metricsRetentionDays))
                ->delete();

            Log::info('Database performance metrics cleanup completed');
        } catch (\Exception $e) {
            Log::error('Failed to cleanup old metrics', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
