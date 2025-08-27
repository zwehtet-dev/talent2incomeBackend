<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use SessionHandlerInterface;

class DistributedSessionHandler implements SessionHandlerInterface
{
    protected string $connection;
    protected int $lifetime;
    protected string $prefix;

    public function __construct(string $connection = 'session', int $lifetime = 7200)
    {
        $this->connection = $connection;
        $this->lifetime = $lifetime;
        $this->prefix = config('cache.prefix') . 'session:';
    }

    /**
     * Open session
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * Close session
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Read session data
     */
    public function read(string $id): string|false
    {
        try {
            $redis = Redis::connection($this->connection);
            $data = $redis->get($this->prefix . $id);

            return $data !== null ? $data : '';
        } catch (\Exception $e) {
            \Log::error('Session read error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Write session data
     */
    public function write(string $id, string $data): bool
    {
        try {
            $redis = Redis::connection($this->connection);
            $key = $this->prefix . $id;

            // Store session data with expiration
            $redis->setex($key, $this->lifetime, $data);

            // Store additional metadata for session management
            $metadata = [
                'last_activity' => time(),
                'user_agent' => request()->header('User-Agent', ''),
                'ip_address' => request()->ip(),
            ];

            $redis->setex($key . ':meta', $this->lifetime, serialize($metadata));

            return true;
        } catch (\Exception $e) {
            \Log::error('Session write error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Destroy session
     */
    public function destroy(string $id): bool
    {
        try {
            $redis = Redis::connection($this->connection);
            $key = $this->prefix . $id;

            $redis->del($key);
            $redis->del($key . ':meta');

            return true;
        } catch (\Exception $e) {
            \Log::error('Session destroy error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Garbage collection
     */
    public function gc(int $max_lifetime): int|false
    {
        // Redis handles expiration automatically, so we don't need to do anything here
        // But we can clean up orphaned metadata
        try {
            $redis = Redis::connection($this->connection);
            $pattern = $this->prefix . '*:meta';
            $keys = $redis->keys($pattern);

            $cleaned = 0;
            foreach ($keys as $key) {
                $sessionKey = str_replace(':meta', '', $key);
                if (! $redis->exists($sessionKey)) {
                    $redis->del($key);
                    $cleaned++;
                }
            }

            return $cleaned;
        } catch (\Exception $e) {
            \Log::error('Session GC error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get active sessions count
     */
    public function getActiveSessionsCount(): int
    {
        try {
            $redis = Redis::connection($this->connection);
            $pattern = $this->prefix . '*';
            $keys = $redis->keys($pattern);

            // Filter out metadata keys
            $sessionKeys = array_filter($keys, function ($key) {
                return ! str_ends_with($key, ':meta');
            });

            return count($sessionKeys);
        } catch (\Exception $e) {
            \Log::error('Session count error: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Get session metadata
     */
    public function getSessionMetadata(string $id): ?array
    {
        try {
            $redis = Redis::connection($this->connection);
            $key = $this->prefix . $id . ':meta';
            $data = $redis->get($key);

            return $data ? unserialize($data) : null;
        } catch (\Exception $e) {
            \Log::error('Session metadata error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions(): int
    {
        try {
            $redis = Redis::connection($this->connection);
            $pattern = $this->prefix . '*';
            $keys = $redis->keys($pattern);

            $cleaned = 0;
            foreach ($keys as $key) {
                $ttl = $redis->ttl($key);
                if ($ttl === -2) { // Key doesn't exist
                    $cleaned++;
                }
            }

            return $cleaned;
        } catch (\Exception $e) {
            \Log::error('Session cleanup error: ' . $e->getMessage());

            return 0;
        }
    }
}
