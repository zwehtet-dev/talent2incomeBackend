<?php

namespace Tests\Unit;

use App\Services\DatabaseConnectionPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseConnectionPoolServiceTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseConnectionPoolService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DatabaseConnectionPoolService();
    }

    public function test_can_get_connection(): void
    {
        $connection = $this->service->getConnection('read');

        $this->assertInstanceOf(\Illuminate\Database\ConnectionInterface::class, $connection);
    }

    public function test_can_get_different_connection_types(): void
    {
        $readConnection = $this->service->getConnection('read');
        $writeConnection = $this->service->getConnection('write');
        $analyticsConnection = $this->service->getConnection('analytics');

        $this->assertInstanceOf(\Illuminate\Database\ConnectionInterface::class, $readConnection);
        $this->assertInstanceOf(\Illuminate\Database\ConnectionInterface::class, $writeConnection);
        $this->assertInstanceOf(\Illuminate\Database\ConnectionInterface::class, $analyticsConnection);
    }

    public function test_can_release_connection(): void
    {
        $connection = $this->service->getConnection('read');

        // This should not throw an exception
        $this->service->releaseConnection('read', $connection);

        $this->assertTrue(true);
    }

    public function test_can_get_pool_stats(): void
    {
        $stats = $this->service->getPoolStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('pools', $stats);
        $this->assertArrayHasKey('configuration', $stats);
        $this->assertArrayHasKey('health', $stats);
    }

    public function test_can_cleanup_stale_connections(): void
    {
        // This should not throw an exception
        $this->service->cleanupStaleConnections();

        $this->assertTrue(true);
    }

    public function test_can_maintain_minimum_connections(): void
    {
        // This should not throw an exception
        $this->service->maintainMinimumConnections();

        $this->assertTrue(true);
    }

    public function test_can_get_load_balanced_connection(): void
    {
        $connection = $this->service->getLoadBalancedConnection('select');

        $this->assertInstanceOf(\Illuminate\Database\ConnectionInterface::class, $connection);
    }

    public function test_pool_health_status(): void
    {
        $stats = $this->service->getPoolStats();

        foreach ($stats['health'] as $poolType => $health) {
            $this->assertArrayHasKey('utilization_percent', $health);
            $this->assertArrayHasKey('status', $health);
            $this->assertArrayHasKey('available_connections', $health);

            $this->assertContains($health['status'], ['healthy', 'warning', 'critical']);
        }
    }

    public function test_connection_pool_configuration(): void
    {
        $stats = $this->service->getPoolStats();
        $config = $stats['configuration'];

        $this->assertArrayHasKey('max_connections', $config);
        $this->assertArrayHasKey('min_connections', $config);
        $this->assertArrayHasKey('connection_timeout', $config);

        $this->assertIsInt($config['max_connections']);
        $this->assertIsInt($config['min_connections']);
        $this->assertIsInt($config['connection_timeout']);
    }

    public function test_handles_invalid_pool_type(): void
    {
        // Should fallback to 'read' pool for invalid types
        $connection = $this->service->getConnection('invalid_type');

        $this->assertInstanceOf(\Illuminate\Database\ConnectionInterface::class, $connection);
    }

    public function test_load_balancing_operation_detection(): void
    {
        $readConnection = $this->service->getLoadBalancedConnection('select');
        $writeConnection = $this->service->getLoadBalancedConnection('insert');
        $analyticsConnection = $this->service->getLoadBalancedConnection('analytics');

        $this->assertInstanceOf(\Illuminate\Database\ConnectionInterface::class, $readConnection);
        $this->assertInstanceOf(\Illuminate\Database\ConnectionInterface::class, $writeConnection);
        $this->assertInstanceOf(\Illuminate\Database\ConnectionInterface::class, $analyticsConnection);
    }
}
