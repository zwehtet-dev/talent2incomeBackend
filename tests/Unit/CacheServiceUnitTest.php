<?php

namespace Tests\Unit;

use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheServiceUnitTest extends TestCase
{
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

    public function test_cache_query_basic_functionality(): void
    {
        $result = $this->cacheService->cacheQuery(
            'test:basic',
            fn () => ['data' => 'test'],
            300,
            ['test']
        );

        $this->assertSame(['data' => 'test'], $result);

        // Verify data is cached by calling again with different callback
        $cachedResult = $this->cacheService->cacheQuery(
            'test:basic',
            fn () => ['data' => 'different'],
            300,
            ['test']
        );

        $this->assertSame(['data' => 'test'], $cachedResult);
    }

    public function test_cache_search_results_basic(): void
    {
        $searchQuery = 'web development';
        $filters = ['category_id' => 1];

        $result = $this->cacheService->cacheSearchResults(
            $searchQuery,
            $filters,
            fn () => ['results' => 'search data']
        );

        $this->assertSame(['results' => 'search data'], $result);
    }

    public function test_session_data_operations(): void
    {
        $sessionId = 'test_session_123';
        $sessionData = [
            'user_id' => 1,
            'preferences' => ['theme' => 'dark'],
        ];

        $this->cacheService->storeSessionData($sessionId, $sessionData);

        $retrievedData = $this->cacheService->getSessionData($sessionId);

        $this->assertSame($sessionData, $retrievedData);
    }

    public function test_invalidate_by_tags(): void
    {
        // Cache some data with tags
        $this->cacheService->cacheQuery(
            'test:item1',
            fn () => ['item' => '1'],
            300,
            ['items', 'item:1']
        );

        $this->cacheService->cacheQuery(
            'test:item2',
            fn () => ['item' => '2'],
            300,
            ['items', 'item:2']
        );

        // Invalidate by tag
        $this->cacheService->invalidateByTags(['items']);

        // Verify data is no longer cached
        $result1 = $this->cacheService->cacheQuery(
            'test:item1',
            fn () => ['new' => 'data1'],
            300,
            ['items', 'item:1']
        );

        $this->assertSame(['new' => 'data1'], $result1);
    }

    public function test_cache_constants(): void
    {
        $this->assertSame(300, CacheService::SHORT_TTL);
        $this->assertSame(1800, CacheService::MEDIUM_TTL);
        $this->assertSame(3600, CacheService::LONG_TTL);
        $this->assertSame(86400, CacheService::VERY_LONG_TTL);
    }

    public function test_cache_key_prefixes(): void
    {
        $this->assertSame('user:', CacheService::USER_PREFIX);
        $this->assertSame('job:', CacheService::JOB_PREFIX);
        $this->assertSame('skill:', CacheService::SKILL_PREFIX);
        $this->assertSame('search:', CacheService::SEARCH_PREFIX);
        $this->assertSame('api:', CacheService::API_PREFIX);
        $this->assertSame('query:', CacheService::QUERY_PREFIX);
        $this->assertSame('session:', CacheService::SESSION_PREFIX);
    }
}
