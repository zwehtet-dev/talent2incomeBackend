<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Job;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();

        // Use array cache for testing to avoid Redis dependency
        config(['cache.default' => 'array']);

        $this->cacheService = app(CacheService::class);

        // Clear cache before each test
        Cache::flush();
    }

    public function test_cache_query_stores_and_retrieves_data(): void
    {
        $user = User::factory()->create();

        $result = $this->cacheService->cacheQuery(
            'test:user:' . $user->id,
            fn () => $user->toArray(),
            300,
            ['users']
        );

        $this->assertSame($user->toArray(), $result);

        // Verify data is cached
        $cachedResult = $this->cacheService->cacheQuery(
            'test:user:' . $user->id,
            fn () => ['different' => 'data'],
            300,
            ['users']
        );

        $this->assertSame($user->toArray(), $cachedResult);
    }

    public function test_cache_user_data_with_invalidation(): void
    {
        $user = User::factory()->create();

        $result = $this->cacheService->cacheUserData(
            $user->id,
            'profile',
            fn () => $user->toArray()
        );

        $this->assertSame($user->toArray(), $result);

        // Test invalidation
        $this->cacheService->invalidateUserCache($user->id);

        $newResult = $this->cacheService->cacheUserData(
            $user->id,
            'profile',
            fn () => ['updated' => 'data']
        );

        $this->assertSame(['updated' => 'data'], $newResult);
    }

    public function test_cache_job_data_with_invalidation(): void
    {
        $category = Category::factory()->create();
        $user = User::factory()->create();
        $job = Job::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $result = $this->cacheService->cacheJobData(
            $job->id,
            'details',
            fn () => $job->toArray()
        );

        $this->assertSame($job->toArray(), $result);

        // Test invalidation
        $this->cacheService->invalidateJobCache($job->id);

        $newResult = $this->cacheService->cacheJobData(
            $job->id,
            'details',
            fn () => ['updated' => 'job']
        );

        $this->assertSame(['updated' => 'job'], $newResult);
    }

    public function test_cache_search_results(): void
    {
        $searchQuery = 'web development';
        $filters = ['category_id' => 1, 'budget_min' => 100];

        $result = $this->cacheService->cacheSearchResults(
            $searchQuery,
            $filters,
            fn () => ['results' => 'search data']
        );

        $this->assertSame(['results' => 'search data'], $result);

        // Verify same search returns cached data
        $cachedResult = $this->cacheService->cacheSearchResults(
            $searchQuery,
            $filters,
            fn () => ['different' => 'data']
        );

        $this->assertSame(['results' => 'search data'], $cachedResult);
    }

    public function test_invalidate_by_tags(): void
    {
        // Cache some data with tags
        $this->cacheService->cacheQuery(
            'test:jobs:1',
            fn () => ['job' => 'data'],
            300,
            ['jobs', 'job:1']
        );

        $this->cacheService->cacheQuery(
            'test:jobs:2',
            fn () => ['job2' => 'data'],
            300,
            ['jobs', 'job:2']
        );

        // Invalidate by tag
        $this->cacheService->invalidateByTags(['jobs']);

        // Verify data is no longer cached
        $result1 = $this->cacheService->cacheQuery(
            'test:jobs:1',
            fn () => ['new' => 'data1'],
            300,
            ['jobs', 'job:1']
        );

        $result2 = $this->cacheService->cacheQuery(
            'test:jobs:2',
            fn () => ['new' => 'data2'],
            300,
            ['jobs', 'job:2']
        );

        $this->assertSame(['new' => 'data1'], $result1);
        $this->assertSame(['new' => 'data2'], $result2);
    }

    public function test_session_data_storage(): void
    {
        $sessionId = 'test_session_123';
        $sessionData = [
            'user_id' => 1,
            'preferences' => ['theme' => 'dark'],
            'cart' => ['items' => []],
        ];

        $this->cacheService->storeSessionData($sessionId, $sessionData);

        $retrievedData = $this->cacheService->getSessionData($sessionId);

        $this->assertSame($sessionData, $retrievedData);
    }

    public function test_session_data_expiration(): void
    {
        $sessionId = 'test_session_expire';
        $sessionData = ['test' => 'data'];

        // Store with very short TTL
        $this->cacheService->storeSessionData($sessionId, $sessionData, 1);

        // Should be available immediately
        $this->assertSame($sessionData, $this->cacheService->getSessionData($sessionId));

        // Wait for expiration
        sleep(2);

        // Should be null after expiration
        $this->assertNull($this->cacheService->getSessionData($sessionId));
    }

    public function test_warm_up_cache(): void
    {
        // Create test data
        Category::factory()->create(['is_active' => true]);
        $user = User::factory()->create();
        Job::factory()->create([
            'user_id' => $user->id,
            'status' => 'open',
            'is_urgent' => true,
        ]);

        // Warm up cache
        $this->cacheService->warmUpCache();

        // Verify cached data exists
        $categories = Cache::tags(['categories'])->get('query:categories:active');
        $featuredJobs = Cache::tags(['jobs'])->get('query:jobs:featured');

        $this->assertNotNull($categories);
        $this->assertNotNull($featuredJobs);
    }

    public function test_cache_stats(): void
    {
        // Store some test data
        Cache::put('test:key1', 'value1', 300);
        Cache::put('test:key2', 'value2', 300);

        try {
            $stats = $this->cacheService->getCacheStats();

            $this->assertIsArray($stats);
            $this->assertArrayHasKey('memory_usage', $stats);
            $this->assertArrayHasKey('total_keys', $stats);
            $this->assertArrayHasKey('hit_rate', $stats);
            $this->assertArrayHasKey('connections', $stats);
        } catch (\Exception $e) {
            // Skip test if Redis is not available
            $this->markTestSkipped('Redis not available for testing: ' . $e->getMessage());
        }
    }

    public function test_cache_key_building(): void
    {
        $reflection = new \ReflectionClass($this->cacheService);
        $method = $reflection->getMethod('buildKey');
        $method->setAccessible(true);

        $key = $method->invokeArgs($this->cacheService, ['prefix', 'middle', 'suffix']);
        $this->assertSame('prefix:middle:suffix', $key);

        // Test with null values
        $key = $method->invokeArgs($this->cacheService, ['prefix', null, 'suffix']);
        $this->assertSame('prefix:suffix', $key);
    }
}
