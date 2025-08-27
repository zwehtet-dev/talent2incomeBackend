<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    /**
     * Perform comprehensive health check
     */
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
        ];

        $overall = collect($checks)->every(fn ($check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $overall ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
        ], $overall ? 200 : 503);
    }

    /**
     * Simple health check endpoint
     */
    public function simple(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'response_time_ms' => $responseTime,
                'connection' => DB::connection()->getName(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache functionality
     */
    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = 'health_check_' . time();
            $value = 'test_value';

            Cache::put($key, $value, 60);
            $retrieved = Cache::get($key);
            Cache::forget($key);

            $responseTime = round((microtime(true) - $start) * 1000, 2);

            if ($retrieved === $value) {
                return [
                    'status' => 'ok',
                    'response_time_ms' => $responseTime,
                    'driver' => config('cache.default'),
                ];
            } else {
                return [
                    'status' => 'error',
                    'error' => 'Cache value mismatch',
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Redis connectivity
     */
    private function checkRedis(): array
    {
        try {
            if (! extension_loaded('redis')) {
                return [
                    'status' => 'warning',
                    'message' => 'Redis extension not loaded',
                ];
            }

            $start = microtime(true);
            $response = Redis::ping();
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'response_time_ms' => $responseTime,
                'ping_response' => $response,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check storage accessibility
     */
    private function checkStorage(): array
    {
        try {
            $storagePath = storage_path('app');
            $testFile = $storagePath . '/health_check_' . time() . '.txt';
            $testContent = 'health check test';

            // Test write
            if (! file_put_contents($testFile, $testContent)) {
                return [
                    'status' => 'error',
                    'error' => 'Cannot write to storage',
                ];
            }

            // Test read
            $readContent = file_get_contents($testFile);
            if ($readContent !== $testContent) {
                return [
                    'status' => 'error',
                    'error' => 'Storage read/write mismatch',
                ];
            }

            // Cleanup
            unlink($testFile);

            return [
                'status' => 'ok',
                'writable' => is_writable($storagePath),
                'free_space_mb' => round(disk_free_space($storagePath) / 1024 / 1024, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue functionality
     */
    private function checkQueue(): array
    {
        try {
            $connection = config('queue.default');
            $queueSize = 0;

            // Get queue size based on driver
            switch ($connection) {
                case 'database':
                    $queueSize = DB::table('jobs')->count();

                    break;
                case 'redis':
                    // This is a simplified check
                    $queueSize = 0; // Would need more complex logic for Redis queues

                    break;
            }

            return [
                'status' => 'ok',
                'connection' => $connection,
                'pending_jobs' => $queueSize,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }
}
