<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseConnectionPoolService
{
    private array $connectionPools = [];
    private array $connectionStats = [];
    private int $maxConnections;
    private int $minConnections;
    private int $connectionTimeout;

    public function __construct()
    {
        $this->maxConnections = config('database.pool.max_connections', 20);
        $this->minConnections = config('database.pool.min_connections', 5);
        $this->connectionTimeout = config('database.pool.connection_timeout', 30);

        $this->initializeConnectionPools();
    }

    /**
     * Get connection from pool based on operation type
     */
    public function getConnection(string $type = 'read'): ConnectionInterface
    {
        $pool = $this->connectionPools[$type] ?? $this->connectionPools['read'];

        // Try to get an idle connection
        $connection = $this->getIdleConnection($type);

        if (! $connection) {
            // Create new connection if under limit
            if ($this->canCreateNewConnection($type)) {
                $connection = $this->createNewConnection($type);
            } else {
                // Wait for available connection or use default
                $connection = $this->waitForConnection($type);
            }
        }

        $this->markConnectionActive($type, $connection);

        return $connection;
    }

    /**
     * Release connection back to pool
     */
    public function releaseConnection(string $type, ConnectionInterface $connection): void
    {
        foreach ($this->connectionPools[$type] as $connectionId => &$connectionData) {
            if ($connectionData['connection'] === $connection) {
                $connectionData['active'] = false;
                $connectionData['last_used'] = now();

                $this->connectionStats[$type]['active']--;
                $this->connectionStats[$type]['idle']++;

                Log::debug('Connection released to pool', [
                    'type' => $type,
                    'connection_id' => $connectionId,
                ]);

                break;
            }
        }
    }

    /**
     * Clean up stale connections
     */
    public function cleanupStaleConnections(): void
    {
        $staleThreshold = now()->subMinutes(30);

        foreach ($this->connectionPools as $type => &$pool) {
            foreach ($pool as $connectionId => $connectionData) {
                if (! $connectionData['active'] &&
                    $connectionData['last_used']->lt($staleThreshold)) {

                    $this->removeConnection($type, $connectionId);
                }
            }
        }
    }

    /**
     * Get connection pool statistics
     */
    public function getPoolStats(): array
    {
        return [
            'pools' => $this->connectionStats,
            'configuration' => [
                'max_connections' => $this->maxConnections,
                'min_connections' => $this->minConnections,
                'connection_timeout' => $this->connectionTimeout,
            ],
            'health' => $this->getPoolHealth(),
        ];
    }

    /**
     * Ensure minimum connections are maintained
     */
    public function maintainMinimumConnections(): void
    {
        foreach ($this->connectionStats as $type => $stats) {
            $needed = $this->minConnections - $stats['total'];

            for ($i = 0; $i < $needed; $i++) {
                if ($this->canCreateNewConnection($type)) {
                    $this->createNewConnection($type);
                }
            }
        }
    }

    /**
     * Load balance connections across available pools
     */
    public function getLoadBalancedConnection(string $operation = 'read'): ConnectionInterface
    {
        $poolType = $this->determinePoolType($operation);
        $leastUtilizedPool = $this->findLeastUtilizedPool($poolType);

        return $this->getConnection($leastUtilizedPool);
    }

    /**
     * Initialize connection pools for different database operations
     */
    private function initializeConnectionPools(): void
    {
        $this->connectionPools = [
            'read' => [],
            'write' => [],
            'analytics' => [],
        ];

        $this->connectionStats = [
            'read' => ['active' => 0, 'idle' => 0, 'total' => 0],
            'write' => ['active' => 0, 'idle' => 0, 'total' => 0],
            'analytics' => ['active' => 0, 'idle' => 0, 'total' => 0],
        ];
    }

    /**
     * Get idle connection from pool
     */
    private function getIdleConnection(string $type): ?ConnectionInterface
    {
        $pool = $this->connectionPools[$type];

        foreach ($pool as $connectionId => $connectionData) {
            if (! $connectionData['active'] && $this->isConnectionValid($connectionData['connection'])) {
                return $connectionData['connection'];
            }
        }

        return null;
    }

    /**
     * Check if we can create a new connection
     */
    private function canCreateNewConnection(string $type): bool
    {
        return $this->connectionStats[$type]['total'] < $this->maxConnections;
    }

    /**
     * Create new database connection
     */
    private function createNewConnection(string $type): ConnectionInterface
    {
        $connectionName = $this->getConnectionName($type);
        $connection = DB::connection($connectionName);

        $connectionId = uniqid($type . '_');
        $this->connectionPools[$type][$connectionId] = [
            'connection' => $connection,
            'active' => false,
            'created_at' => now(),
            'last_used' => now(),
        ];

        $this->connectionStats[$type]['total']++;
        $this->connectionStats[$type]['idle']++;

        Log::info('New database connection created', [
            'type' => $type,
            'connection_id' => $connectionId,
            'total_connections' => $this->connectionStats[$type]['total'],
        ]);

        return $connection;
    }

    /**
     * Get connection name based on type
     */
    private function getConnectionName(string $type): string
    {
        return match ($type) {
            'read' => config('database.pool.read_connection', 'mysql'),
            'write' => config('database.pool.write_connection', 'mysql'),
            'analytics' => config('database.pool.analytics_connection', 'mysql'),
            default => 'mysql',
        };
    }

    /**
     * Wait for available connection
     */
    private function waitForConnection(string $type): ConnectionInterface
    {
        $startTime = time();

        while ((time() - $startTime) < $this->connectionTimeout) {
            $connection = $this->getIdleConnection($type);
            if ($connection) {
                return $connection;
            }

            usleep(100000); // Wait 100ms
        }

        // Fallback to default connection
        Log::warning('Connection pool timeout, using default connection', [
            'type' => $type,
            'timeout' => $this->connectionTimeout,
        ]);

        return DB::connection();
    }

    /**
     * Mark connection as active
     */
    private function markConnectionActive(string $type, ConnectionInterface $connection): void
    {
        foreach ($this->connectionPools[$type] as $connectionId => &$connectionData) {
            if ($connectionData['connection'] === $connection) {
                $connectionData['active'] = true;
                $connectionData['last_used'] = now();

                $this->connectionStats[$type]['active']++;
                $this->connectionStats[$type]['idle']--;

                break;
            }
        }
    }

    /**
     * Check if connection is still valid
     */
    private function isConnectionValid(ConnectionInterface $connection): bool
    {
        try {
            $connection->getPdo()->query('SELECT 1');

            return true;
        } catch (\Exception $e) {
            Log::warning('Invalid connection detected', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Remove connection from pool
     */
    private function removeConnection(string $type, string $connectionId): void
    {
        if (isset($this->connectionPools[$type][$connectionId])) {
            $connectionData = $this->connectionPools[$type][$connectionId];

            try {
                $connectionData['connection']->disconnect();
            } catch (\Exception $e) {
                Log::warning('Error disconnecting connection', ['error' => $e->getMessage()]);
            }

            unset($this->connectionPools[$type][$connectionId]);

            if ($connectionData['active']) {
                $this->connectionStats[$type]['active']--;
            } else {
                $this->connectionStats[$type]['idle']--;
            }
            $this->connectionStats[$type]['total']--;

            Log::info('Stale connection removed', [
                'type' => $type,
                'connection_id' => $connectionId,
            ]);
        }
    }

    /**
     * Get pool health metrics
     */
    private function getPoolHealth(): array
    {
        $health = [];

        foreach ($this->connectionStats as $type => $stats) {
            $utilization = $stats['total'] > 0 ? ($stats['active'] / $stats['total']) * 100 : 0;

            $health[$type] = [
                'utilization_percent' => round($utilization, 2),
                'status' => $this->getPoolStatus($utilization),
                'available_connections' => $stats['idle'],
            ];
        }

        return $health;
    }

    /**
     * Get pool status based on utilization
     */
    private function getPoolStatus(float $utilization): string
    {
        if ($utilization < 50) {
            return 'healthy';
        }
        if ($utilization < 80) {
            return 'warning';
        }

        return 'critical';
    }

    /**
     * Determine pool type based on operation
     */
    private function determinePoolType(string $operation): array
    {
        return match (strtolower($operation)) {
            'select', 'read', 'find' => ['read'],
            'insert', 'update', 'delete', 'write' => ['write'],
            'analytics', 'report' => ['analytics', 'read'],
            default => ['read', 'write'],
        };
    }

    /**
     * Find least utilized pool from available options
     */
    private function findLeastUtilizedPool(array $poolTypes): string
    {
        $leastUtilized = null;
        $lowestUtilization = 100;

        foreach ($poolTypes as $type) {
            if (! isset($this->connectionStats[$type])) {
                continue;
            }

            $stats = $this->connectionStats[$type];
            $utilization = $stats['total'] > 0 ? ($stats['active'] / $stats['total']) * 100 : 0;

            if ($utilization < $lowestUtilization) {
                $lowestUtilization = $utilization;
                $leastUtilized = $type;
            }
        }

        return $leastUtilized ?? 'read';
    }
}
