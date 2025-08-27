<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CacheService
{
    /**
     * Cache TTL constants (in seconds)
     */
    public const SHORT_TTL = 300;      // 5 minutes
    public const MEDIUM_TTL = 1800;    // 30 minutes
    public const LONG_TTL = 3600;      // 1 hour
    public const VERY_LONG_TTL = 86400; // 24 hours

    /**
     * Cache key prefixes
     */
    public const USER_PREFIX = 'user:';
    public const JOB_PREFIX = 'job:';
    public const SKILL_PREFIX = 'skill:';
    public const SEARCH_PREFIX = 'search:';
    public const API_PREFIX = 'api:';
    public const QUERY_PREFIX = 'query:';
    public const SESSION_PREFIX = 'session:';

    /**
     * Cache a query result with intelligent invalidation tags
     */
    public function cacheQuery(string $key, callable $callback, int $ttl = self::MEDIUM_TTL, array $tags = []): mixed
    {
        $cacheKey = $this->buildKey(self::QUERY_PREFIX, $key);

        return Cache::tags($tags)->remember($cacheKey, $ttl, $callback);
    }

    /**
     * Cache API response with conditional request support
     */
    public function cacheApiResponse(Request $request, callable $callback, int $ttl = self::SHORT_TTL): mixed
    {
        $cacheKey = $this->buildApiCacheKey($request);

        // Check for conditional request headers
        $etag = Cache::get($cacheKey . ':etag');
        if ($etag && $request->header('If-None-Match') === $etag) {
            return response('', 304);
        }

        $lastModified = Cache::get($cacheKey . ':last_modified');
        if ($lastModified && $request->header('If-Modified-Since') === $lastModified) {
            return response('', 304);
        }

        $response = Cache::remember($cacheKey, $ttl, $callback);

        // Store ETag and Last-Modified for conditional requests
        $responseEtag = md5(serialize($response));
        $responseLastModified = now()->toRfc7231String();

        Cache::put($cacheKey . ':etag', $responseEtag, $ttl);
        Cache::put($cacheKey . ':last_modified', $responseLastModified, $ttl);

        return $response;
    }

    /**
     * Cache search results with real-time invalidation
     */
    public function cacheSearchResults(string $query, array $filters, callable $callback, int $ttl = self::SHORT_TTL): mixed
    {
        $cacheKey = $this->buildSearchCacheKey($query, $filters);
        $tags = ['search', 'jobs', 'skills'];

        return Cache::tags($tags)->remember($cacheKey, $ttl, $callback);
    }

    /**
     * Cache user data with profile-specific invalidation
     */
    public function cacheUserData(int $userId, string $dataType, callable $callback, int $ttl = self::MEDIUM_TTL): mixed
    {
        $cacheKey = $this->buildKey(self::USER_PREFIX, $userId, $dataType);
        $tags = ['users', "user:{$userId}"];

        return Cache::tags($tags)->remember($cacheKey, $ttl, $callback);
    }

    /**
     * Cache job data with status-aware invalidation
     */
    public function cacheJobData(int $jobId, string $dataType, callable $callback, int $ttl = self::MEDIUM_TTL): mixed
    {
        $cacheKey = $this->buildKey(self::JOB_PREFIX, $jobId, $dataType);
        $tags = ['jobs', "job:{$jobId}"];

        return Cache::tags($tags)->remember($cacheKey, $ttl, $callback);
    }

    /**
     * Cache skill data with availability-aware invalidation
     */
    public function cacheSkillData(int $skillId, string $dataType, callable $callback, int $ttl = self::MEDIUM_TTL): mixed
    {
        $cacheKey = $this->buildKey(self::SKILL_PREFIX, $skillId, $dataType);
        $tags = ['skills', "skill:{$skillId}"];

        return Cache::tags($tags)->remember($cacheKey, $ttl, $callback);
    }

    /**
     * Invalidate cache by tags
     */
    public function invalidateByTags(array $tags): void
    {
        Cache::tags($tags)->flush();
    }

    /**
     * Invalidate user-specific cache
     */
    public function invalidateUserCache(int $userId): void
    {
        $this->invalidateByTags(["user:{$userId}"]);
    }

    /**
     * Invalidate job-specific cache
     */
    public function invalidateJobCache(int $jobId): void
    {
        $this->invalidateByTags(["job:{$jobId}", 'search']);
    }

    /**
     * Invalidate skill-specific cache
     */
    public function invalidateSkillCache(int $skillId): void
    {
        $this->invalidateByTags(["skill:{$skillId}", 'search']);
    }

    /**
     * Invalidate search cache
     */
    public function invalidateSearchCache(): void
    {
        $this->invalidateByTags(['search']);
    }

    /**
     * Store session data in distributed cache
     */
    public function storeSessionData(string $sessionId, array $data, int $ttl = self::LONG_TTL): void
    {
        $cacheKey = $this->buildKey(self::SESSION_PREFIX, $sessionId);
        Cache::put($cacheKey, $data, $ttl);
    }

    /**
     * Retrieve session data from distributed cache
     */
    public function getSessionData(string $sessionId): ?array
    {
        $cacheKey = $this->buildKey(self::SESSION_PREFIX, $sessionId);

        return Cache::get($cacheKey);
    }

    /**
     * Warm up cache with frequently accessed data
     */
    public function warmUpCache(): void
    {
        // Warm up popular categories
        $this->cacheQuery('categories:active', function () {
            return \App\Models\Category::where('is_active', true)->get();
        }, self::VERY_LONG_TTL, ['categories']);

        // Warm up featured jobs
        $this->cacheQuery('jobs:featured', function () {
            return \App\Models\Job::where('status', 'open')
                ->where('is_urgent', true)
                ->with(['user', 'category'])
                ->limit(10)
                ->get();
        }, self::MEDIUM_TTL, ['jobs']);

        // Warm up top-rated skills
        $this->cacheQuery('skills:top_rated', function () {
            return \App\Models\Skill::where('is_available', true)
                ->whereHas('user', function ($query) {
                    $query->where('average_rating', '>=', 4.5);
                })
                ->with(['user', 'category'])
                ->limit(20)
                ->get();
        }, self::MEDIUM_TTL, ['skills']);
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        try {
            $redis = Redis::connection('cache');

            return [
                'memory_usage' => $redis->info('memory')['used_memory_human'] ?? 'N/A',
                'total_keys' => $redis->dbsize(),
                'hit_rate' => $this->calculateHitRate(),
                'connections' => $redis->info('clients')['connected_clients'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'memory_usage' => 'N/A',
                'total_keys' => 0,
                'hit_rate' => 0,
                'connections' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build cache key from components
     */
    private function buildKey(...$components): string
    {
        return implode(':', array_filter($components, fn ($component) => $component !== null && $component !== ''));
    }

    /**
     * Build API cache key from request
     */
    private function buildApiCacheKey(Request $request): string
    {
        $uri = $request->getRequestUri();
        $method = $request->getMethod();
        $params = $request->query();
        ksort($params);

        $key = $method . ':' . $uri . ':' . md5(serialize($params));

        return $this->buildKey(self::API_PREFIX, $key);
    }

    /**
     * Build search cache key
     */
    private function buildSearchCacheKey(string $query, array $filters): string
    {
        ksort($filters);
        $filterHash = md5(serialize($filters));
        $queryHash = md5($query);

        return $this->buildKey(self::SEARCH_PREFIX, $queryHash, $filterHash);
    }

    /**
     * Calculate cache hit rate
     */
    private function calculateHitRate(): float
    {
        try {
            $redis = Redis::connection('cache');
            $stats = $redis->info('stats');

            $hits = $stats['keyspace_hits'] ?? 0;
            $misses = $stats['keyspace_misses'] ?? 0;
            $total = $hits + $misses;

            return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
