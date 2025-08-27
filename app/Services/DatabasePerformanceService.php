<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabasePerformanceService
{
    private array $slowQueryThreshold = [
        'select' => 1.0, // 1 second
        'insert' => 0.5, // 500ms
        'update' => 0.5, // 500ms
        'delete' => 0.5, // 500ms
    ];

    private array $queryMetrics = [];

    public function __construct()
    {
        $this->enableQueryLogging();
    }

    /**
     * Enable query logging and monitoring
     */
    public function enableQueryLogging(): void
    {
        DB::listen(function ($query) {
            $this->logQuery($query);
            $this->analyzeQueryPerformance($query);
        });
    }

    /**
     * Run EXPLAIN analysis on a query
     */
    public function explainQuery(string $sql, array $bindings = []): Collection
    {
        try {
            $explainSql = 'EXPLAIN ' . $sql;
            $results = DB::select($explainSql, $bindings);

            return collect($results)->map(function ($row) {
                return (array) $row;
            });
        } catch (\Exception $e) {
            Log::error('Failed to explain query', [
                'sql' => $sql,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Get database performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        try {
            $cacheKey = 'db_performance_metrics';

            return Cache::remember($cacheKey, 300, function () {
                return [
                    'connection_stats' => $this->getConnectionStats(),
                    'query_stats' => $this->getQueryStats(),
                    'slow_queries' => $this->getSlowQueries(),
                    'table_stats' => $this->getTableStats(),
                    'index_usage' => $this->getIndexUsage(),
                ];
            });
        } catch (\Exception $e) {
            // If caching fails, return metrics directly
            Log::warning('Cache unavailable, returning metrics directly', [
                'error' => $e->getMessage(),
            ]);

            return [
                'connection_stats' => $this->getConnectionStats(),
                'query_stats' => $this->getQueryStats(),
                'slow_queries' => $this->getSlowQueries(),
                'table_stats' => $this->getTableStats(),
                'index_usage' => $this->getIndexUsage(),
            ];
        }
    }

    /**
     * Optimize table by running OPTIMIZE TABLE
     */
    public function optimizeTable(string $tableName): bool
    {
        try {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                // SQLite uses VACUUM for optimization
                DB::statement('VACUUM');
                Log::info('Database vacuumed successfully (SQLite optimization)');

                return true;
            }

            DB::statement("OPTIMIZE TABLE {$tableName}");
            Log::info('Table optimized successfully', ['table' => $tableName]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to optimize table', [
                'table' => $tableName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Analyze table for optimization recommendations
     */
    public function analyzeTable(string $tableName): bool
    {
        try {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                // SQLite uses ANALYZE for statistics
                DB::statement("ANALYZE {$tableName}");
                Log::info('Table analyzed successfully (SQLite)', ['table' => $tableName]);

                return true;
            }

            DB::statement("ANALYZE TABLE {$tableName}");
            Log::info('Table analyzed successfully', ['table' => $tableName]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to analyze table', [
                'table' => $tableName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Clear query metrics
     */
    public function clearMetrics(): void
    {
        $this->queryMetrics = [];
    }

    /**
     * Get current query metrics
     */
    public function getQueryMetrics(): array
    {
        return $this->queryMetrics;
    }

    /**
     * Log query execution details
     * @param mixed $query
     */
    private function logQuery($query): void
    {
        $sql = $query->sql;
        $bindings = $query->bindings;
        $time = $query->time;

        // Store query metrics
        $this->queryMetrics[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'time' => $time,
            'timestamp' => now(),
        ];

        // Log slow queries
        if ($this->isSlowQuery($sql, $time)) {
            Log::channel('slow_queries')->warning('Slow query detected', [
                'sql' => $sql,
                'bindings' => $bindings,
                'execution_time' => $time,
                'threshold' => $this->getQueryThreshold($sql),
            ]);
        }
    }

    /**
     * Analyze query performance and suggest optimizations
     * @param mixed $query
     */
    private function analyzeQueryPerformance($query): void
    {
        $sql = $query->sql;
        $time = $query->time;

        if ($this->isSlowQuery($sql, $time)) {
            $this->generateOptimizationSuggestions($sql, $time);
        }
    }

    /**
     * Check if query is considered slow
     */
    private function isSlowQuery(string $sql, float $time): bool
    {
        $queryType = $this->getQueryType($sql);
        $threshold = $this->slowQueryThreshold[$queryType] ?? 1.0;

        return $time >= $threshold * 1000; // Convert to milliseconds
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
     * Get threshold for query type
     */
    private function getQueryThreshold(string $sql): float
    {
        $queryType = $this->getQueryType($sql);

        return $this->slowQueryThreshold[$queryType] ?? 1.0;
    }

    /**
     * Generate optimization suggestions for slow queries
     */
    private function generateOptimizationSuggestions(string $sql, float $time): void
    {
        $suggestions = [];

        // Check for missing indexes
        if (str_contains($sql, 'where') && ! str_contains($sql, 'index')) {
            $suggestions[] = 'Consider adding indexes on WHERE clause columns';
        }

        // Check for SELECT *
        if (str_contains($sql, 'select *')) {
            $suggestions[] = 'Avoid SELECT * - specify only needed columns';
        }

        // Check for N+1 queries
        if (str_contains($sql, 'limit 1') && $time > 100) {
            $suggestions[] = 'Potential N+1 query - consider eager loading';
        }

        // Check for large OFFSET
        if (preg_match('/offset\s+(\d+)/i', $sql, $matches)) {
            $offset = (int) $matches[1];
            if ($offset > 1000) {
                $suggestions[] = 'Large OFFSET detected - consider cursor-based pagination';
            }
        }

        if (! empty($suggestions)) {
            Log::channel('performance')->info('Query optimization suggestions', [
                'sql' => $sql,
                'execution_time' => $time,
                'suggestions' => $suggestions,
            ]);
        }
    }

    /**
     * Get database connection statistics
     */
    private function getConnectionStats(): array
    {
        try {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                // SQLite doesn't have connection statistics like MySQL
                return [
                    'total_connections' => 1,
                    'max_connections' => 1,
                    'current_connections' => 1,
                    'connection_usage_percent' => 100.0,
                ];
            }

            $stats = DB::select("SHOW STATUS LIKE 'Connections'");
            $maxConnections = DB::select("SHOW VARIABLES LIKE 'max_connections'");
            $threadsConnected = DB::select("SHOW STATUS LIKE 'Threads_connected'");

            return [
                'total_connections' => $stats[0]->Value ?? 0,
                'max_connections' => $maxConnections[0]->Value ?? 0,
                'current_connections' => $threadsConnected[0]->Value ?? 0,
                'connection_usage_percent' => $this->calculateConnectionUsage(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get connection stats', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Calculate connection usage percentage
     */
    private function calculateConnectionUsage(): float
    {
        try {
            $maxConnections = DB::select("SHOW VARIABLES LIKE 'max_connections'")[0]->Value;
            $currentConnections = DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value;

            return ($currentConnections / $maxConnections) * 100;
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Get query execution statistics
     */
    private function getQueryStats(): array
    {
        if (empty($this->queryMetrics)) {
            return [];
        }

        $totalQueries = count($this->queryMetrics);
        $totalTime = array_sum(array_column($this->queryMetrics, 'time'));
        $avgTime = $totalTime / $totalQueries;

        $slowQueries = array_filter($this->queryMetrics, function ($query) {
            return $this->isSlowQuery($query['sql'], $query['time']);
        });

        return [
            'total_queries' => $totalQueries,
            'total_execution_time' => $totalTime,
            'average_execution_time' => $avgTime,
            'slow_queries_count' => count($slowQueries),
            'slow_queries_percentage' => ($totalQueries > 0) ? (count($slowQueries) / $totalQueries) * 100 : 0,
        ];
    }

    /**
     * Get recent slow queries
     */
    private function getSlowQueries(int $limit = 10): array
    {
        $slowQueries = array_filter($this->queryMetrics, function ($query) {
            return $this->isSlowQuery($query['sql'], $query['time']);
        });

        // Sort by execution time descending
        usort($slowQueries, function ($a, $b) {
            return $b['time'] <=> $a['time'];
        });

        return array_slice($slowQueries, 0, $limit);
    }

    /**
     * Get table statistics
     */
    private function getTableStats(): array
    {
        try {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                // SQLite doesn't have information_schema, use sqlite_master
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

                return array_map(function ($table) {
                    return [
                        'name' => $table->name,
                        'rows' => 0, // SQLite doesn't provide easy row count
                        'data_size' => 0,
                        'index_size' => 0,
                        'total_size' => 0,
                    ];
                }, $tables);
            }

            $tables = DB::select('
                SELECT 
                    table_name,
                    table_rows,
                    data_length,
                    index_length,
                    (data_length + index_length) as total_size
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                ORDER BY total_size DESC
            ');

            return array_map(function ($table) {
                return [
                    'name' => $table->table_name,
                    'rows' => $table->table_rows,
                    'data_size' => $table->data_length,
                    'index_size' => $table->index_length,
                    'total_size' => $table->total_size,
                ];
            }, $tables);
        } catch (\Exception $e) {
            Log::error('Failed to get table stats', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Get index usage statistics
     */
    private function getIndexUsage(): array
    {
        try {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                // SQLite doesn't have information_schema.statistics
                return [];
            }

            $indexes = DB::select("
                SELECT 
                    table_name,
                    index_name,
                    column_name,
                    cardinality
                FROM information_schema.statistics 
                WHERE table_schema = DATABASE()
                AND index_name != 'PRIMARY'
                ORDER BY table_name, index_name
            ");

            return array_map(function ($index) {
                return [
                    'table' => $index->table_name,
                    'index' => $index->index_name,
                    'column' => $index->column_name,
                    'cardinality' => $index->cardinality,
                ];
            }, $indexes);
        } catch (\Exception $e) {
            Log::error('Failed to get index usage', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
