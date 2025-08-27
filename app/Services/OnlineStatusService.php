<?php

namespace App\Services;

use App\Events\UserOnlineStatusChanged;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class OnlineStatusService
{
    private const ONLINE_CACHE_KEY = 'user_online_status';
    private const ONLINE_TIMEOUT = 300; // 5 minutes

    /**
     * Mark user as online.
     */
    public function markUserOnline(User $user): void
    {
        $cacheKey = $this->getUserCacheKey($user->id);
        $wasOnline = Cache::has($cacheKey);

        // Update cache with current timestamp
        Cache::put($cacheKey, now()->timestamp, self::ONLINE_TIMEOUT);

        // For non-Redis cache drivers, maintain online users list
        if (! (config('cache.default') === 'redis' && Cache::getStore() instanceof \Illuminate\Cache\RedisStore)) {
            $onlineUsersList = Cache::get('online_users_list', []);
            if (! in_array($user->id, $onlineUsersList)) {
                $onlineUsersList[] = $user->id;
                Cache::put('online_users_list', $onlineUsersList, self::ONLINE_TIMEOUT);
            }
        }

        // Broadcast status change if user wasn't online before
        if (! $wasOnline) {
            broadcast(new UserOnlineStatusChanged($user, true));
        }
    }

    /**
     * Mark user as offline.
     */
    public function markUserOffline(User $user): void
    {
        $cacheKey = $this->getUserCacheKey($user->id);
        $wasOnline = Cache::has($cacheKey);

        // Remove from cache
        Cache::forget($cacheKey);

        // For non-Redis cache drivers, remove from online users list
        if (! (config('cache.default') === 'redis' && Cache::getStore() instanceof \Illuminate\Cache\RedisStore)) {
            $onlineUsersList = Cache::get('online_users_list', []);
            $onlineUsersList = array_filter($onlineUsersList, function ($userId) use ($user) {
                return $userId !== $user->id;
            });
            Cache::put('online_users_list', array_values($onlineUsersList), self::ONLINE_TIMEOUT);
        }

        // Broadcast status change if user was online
        if ($wasOnline) {
            broadcast(new UserOnlineStatusChanged($user, false));
        }
    }

    /**
     * Check if user is online.
     */
    public function isUserOnline(int $userId): bool
    {
        return Cache::has($this->getUserCacheKey($userId));
    }

    /**
     * Get user's last seen timestamp.
     */
    public function getUserLastSeen(int $userId): ?int
    {
        return Cache::get($this->getUserCacheKey($userId));
    }

    /**
     * Get all online users.
     * @return array<int>
     */
    public function getOnlineUsers(): array
    {
        try {
            // Check if we're using Redis cache
            if (config('cache.default') === 'redis' && Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $pattern = $this->getUserCacheKey('*');
                $redis = Redis::connection();
                $keys = $redis->keys($pattern);

                $onlineUsers = [];
                foreach ($keys as $key) {
                    $userId = $this->extractUserIdFromKey($key);
                    if ($userId && $this->isUserOnline($userId)) {
                        $onlineUsers[] = $userId;
                    }
                }

                return $onlineUsers;
            } else {
                // Fallback for non-Redis cache drivers
                // We'll maintain a separate list of online users
                $onlineUsersList = Cache::get('online_users_list', []);

                return array_filter($onlineUsersList, function ($userId) {
                    return $this->isUserOnline($userId);
                });
            }
        } catch (\Exception $e) {
            // Fallback to empty array if cache is not available
            return [];
        }
    }

    /**
     * Get online users count.
     */
    public function getOnlineUsersCount(): int
    {
        return count($this->getOnlineUsers());
    }

    /**
     * Update user's last activity.
     */
    public function updateUserActivity(User $user): void
    {
        $this->markUserOnline($user);
    }

    /**
     * Clean up offline users (called by scheduled job).
     */
    public function cleanupOfflineUsers(): int
    {
        try {
            $cleanedUp = 0;

            if (config('cache.default') === 'redis' && Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $pattern = $this->getUserCacheKey('*');
                $redis = Redis::connection();
                $keys = $redis->keys($pattern);

                foreach ($keys as $key) {
                    $lastSeen = Cache::get($key);
                    if ($lastSeen && (now()->timestamp - $lastSeen) > self::ONLINE_TIMEOUT) {
                        $userId = $this->extractUserIdFromKey($key);
                        if ($userId) {
                            $user = User::find($userId);
                            if ($user) {
                                $this->markUserOffline($user);
                                $cleanedUp++;
                            }
                        }
                    }
                }
            } else {
                // For non-Redis cache drivers, check online users list
                $onlineUsersList = Cache::get('online_users_list', []);
                foreach ($onlineUsersList as $userId) {
                    $lastSeen = $this->getUserLastSeen($userId);
                    if ($lastSeen && (now()->timestamp - $lastSeen) > self::ONLINE_TIMEOUT) {
                        $user = User::find($userId);
                        if ($user instanceof User) {
                            $this->markUserOffline($user);
                            $cleanedUp++;
                        }
                    }
                }
            }

            return $cleanedUp;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get cache key for user.
     */
    private function getUserCacheKey(int|string $userId): string
    {
        return self::ONLINE_CACHE_KEY . ':' . $userId;
    }

    /**
     * Extract user ID from cache key.
     */
    private function extractUserIdFromKey(string $key): ?int
    {
        $parts = explode(':', $key);
        $userId = end($parts);

        return is_numeric($userId) ? (int) $userId : null;
    }
}
