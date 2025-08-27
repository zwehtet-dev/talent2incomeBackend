<?php

namespace App\Console\Commands;

use App\Services\DatabaseConnectionPoolService;
use App\Services\DatabasePerformanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OptimizeDatabasePerformance extends Command
{
    protected $signature = 'db:optimize 
                           {--analyze : Run ANALYZE TABLE on all tables}
                           {--optimize : Run OPTIMIZE TABLE on all tables}
                           {--explain : Show EXPLAIN analysis for slow queries}
                           {--metrics : Display performance metrics}
                           {--cleanup : Clean up stale connections}';

    protected $description = 'Optimize database performance and analyze queries';

    private DatabasePerformanceService $performanceService;
    private DatabaseConnectionPoolService $poolService;

    public function __construct(
        DatabasePerformanceService $performanceService,
        DatabaseConnectionPoolService $poolService
    ) {
        parent::__construct();
        $this->performanceService = $performanceService;
        $this->poolService = $poolService;
    }

    public function handle(): int
    {
        $this->info('Starting database performance optimization...');

        if ($this->option('metrics')) {
            $this->displayMetrics();
        }

        if ($this->option('analyze')) {
            $this->analyzeTables();
        }

        if ($this->option('optimize')) {
            $this->optimizeTables();
        }

        if ($this->option('explain')) {
            $this->explainSlowQueries();
        }

        if ($this->option('cleanup')) {
            $this->cleanupConnections();
        }

        // If no specific options, run all optimizations
        if (! $this->hasOptions()) {
            $this->runFullOptimization();
        }

        $this->info('Database optimization completed.');

        return 0;
    }

    private function hasOptions(): bool
    {
        return $this->option('analyze') ||
               $this->option('optimize') ||
               $this->option('explain') ||
               $this->option('metrics') ||
               $this->option('cleanup');
    }

    private function displayMetrics(): void
    {
        $this->info('Fetching performance metrics...');

        $metrics = $this->performanceService->getPerformanceMetrics();
        $poolStats = $this->poolService->getPoolStats();

        // Display connection stats
        if (isset($metrics['connection_stats'])) {
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Connections', $metrics['connection_stats']['total_connections'] ?? 'N/A'],
                    ['Max Connections', $metrics['connection_stats']['max_connections'] ?? 'N/A'],
                    ['Current Connections', $metrics['connection_stats']['current_connections'] ?? 'N/A'],
                    ['Usage %', round($metrics['connection_stats']['connection_usage_percent'] ?? 0, 2) . '%'],
                ]
            );
        }

        // Display query stats
        if (isset($metrics['query_stats']) && ! empty($metrics['query_stats'])) {
            $this->info("\nQuery Statistics:");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Queries', $metrics['query_stats']['total_queries']],
                    ['Avg Execution Time', round($metrics['query_stats']['average_execution_time'], 2) . 'ms'],
                    ['Slow Queries', $metrics['query_stats']['slow_queries_count']],
                    ['Slow Query %', round($metrics['query_stats']['slow_queries_percentage'], 2) . '%'],
                ]
            );
        }

        // Display pool stats
        $this->info("\nConnection Pool Statistics:");
        foreach ($poolStats['pools'] as $type => $stats) {
            $this->line("Pool: {$type}");
            $this->line("  Active: {$stats['active']}");
            $this->line("  Idle: {$stats['idle']}");
            $this->line("  Total: {$stats['total']}");
        }

        // Display table stats
        if (isset($metrics['table_stats']) && ! empty($metrics['table_stats'])) {
            $this->info("\nTop 10 Largest Tables:");
            $tableData = array_slice($metrics['table_stats'], 0, 10);
            $this->table(
                ['Table', 'Rows', 'Data Size', 'Index Size', 'Total Size'],
                array_map(function ($table) {
                    return [
                        $table['name'],
                        number_format($table['rows']),
                        $this->formatBytes($table['data_size']),
                        $this->formatBytes($table['index_size']),
                        $this->formatBytes($table['total_size']),
                    ];
                }, $tableData)
            );
        }
    }

    private function analyzeTables(): void
    {
        $this->info('Analyzing database tables...');

        $tables = $this->getDatabaseTables();
        $bar = $this->output->createProgressBar(count($tables));

        foreach ($tables as $table) {
            $success = $this->performanceService->analyzeTable($table);

            if ($success) {
                $this->line("✓ Analyzed table: {$table}");
            } else {
                $this->error("✗ Failed to analyze table: {$table}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function optimizeTables(): void
    {
        $this->info('Optimizing database tables...');

        $tables = $this->getDatabaseTables();
        $bar = $this->output->createProgressBar(count($tables));

        foreach ($tables as $table) {
            $success = $this->performanceService->optimizeTable($table);

            if ($success) {
                $this->line("✓ Optimized table: {$table}");
            } else {
                $this->error("✗ Failed to optimize table: {$table}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function explainSlowQueries(): void
    {
        $this->info('Analyzing slow queries...');

        $metrics = $this->performanceService->getQueryMetrics();
        $slowQueries = array_filter($metrics, function ($query) {
            return $query['time'] > 1000; // Queries slower than 1 second
        });

        if (empty($slowQueries)) {
            $this->info('No slow queries found in current session.');

            return;
        }

        foreach (array_slice($slowQueries, 0, 5) as $query) {
            $this->info("\nSlow Query Analysis:");
            $this->line('SQL: ' . substr($query['sql'], 0, 100) . '...');
            $this->line("Execution Time: {$query['time']}ms");

            $explanation = $this->performanceService->explainQuery($query['sql'], $query['bindings']);

            if ($explanation->isNotEmpty()) {
                $this->table(
                    ['Type', 'Table', 'Key', 'Rows', 'Extra'],
                    $explanation->map(function ($row) {
                        return [
                            $row['select_type'] ?? '',
                            $row['table'] ?? '',
                            $row['key'] ?? 'NULL',
                            $row['rows'] ?? '',
                            $row['Extra'] ?? '',
                        ];
                    })->toArray()
                );
            }
        }
    }

    private function cleanupConnections(): void
    {
        $this->info('Cleaning up stale database connections...');

        $this->poolService->cleanupStaleConnections();
        $this->poolService->maintainMinimumConnections();

        $this->info('Connection cleanup completed.');
    }

    private function runFullOptimization(): void
    {
        $this->info('Running full database optimization...');

        $this->analyzeTables();
        $this->optimizeTables();
        $this->cleanupConnections();
        $this->displayMetrics();
    }

    private function getDatabaseTables(): array
    {
        try {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

                return array_map(fn ($table) => $table->name, $tables);
            } else {
                $tables = DB::select('SHOW TABLES');

                return array_map(fn ($table) => array_values((array)$table)[0], $tables);
            }
        } catch (\Exception $e) {
            $this->error('Failed to get database tables: ' . $e->getMessage());

            return [];
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
