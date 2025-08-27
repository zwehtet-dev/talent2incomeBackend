# Advanced Caching Implementation

This document describes the advanced caching strategies implemented in the Talent2Income platform.

## Overview

The platform implements a comprehensive caching system using Redis with intelligent cache invalidation, query result caching, API response caching, distributed session storage, and search result caching with real-time updates.

## Components

### 1. CacheService

The `CacheService` class provides a centralized interface for all caching operations:

- **Query Result Caching**: Cache database query results with intelligent invalidation tags
- **API Response Caching**: Cache API responses with conditional request support (ETag, Last-Modified)
- **Search Result Caching**: Cache search results with real-time invalidation
- **User Data Caching**: Cache user-specific data with profile-aware invalidation
- **Session Data Caching**: Distributed session storage using Redis

### 2. Cache Invalidation Trait

The `CacheInvalidation` trait automatically handles cache invalidation when models are created, updated, or deleted:

- Automatically invalidates related cache when models change
- Supports model-specific invalidation strategies
- Handles relationships and dependencies

### 3. Cache Response Middleware

The `CacheResponseMiddleware` provides HTTP-level caching for API responses:

- Supports conditional requests (If-None-Match, If-Modified-Since)
- Automatic ETag and Last-Modified header generation
- Configurable TTL per route
- Skips caching for admin users

### 4. Distributed Session Handler

The `DistributedSessionHandler` provides Redis-based session storage:

- Distributed session storage across multiple servers
- Session metadata tracking (IP, User-Agent, last activity)
- Automatic session cleanup and garbage collection

## Configuration

### Redis Connections

The system uses multiple Redis databases for different purposes:

```env
REDIS_DB=0              # Default Redis database
REDIS_CACHE_DB=1        # General cache storage
REDIS_SESSION_DB=2      # Session storage
REDIS_QUEUE_DB=3        # Queue storage
REDIS_BROADCAST_DB=4    # Broadcasting
REDIS_SEARCH_DB=5       # Search result cache
REDIS_API_DB=6          # API response cache
```

### Cache Stores

Multiple cache stores are configured:

- `redis`: Default cache store
- `redis_sessions`: Session-specific cache
- `redis_search`: Search result cache
- `redis_api`: API response cache

## Usage Examples

### Basic Query Caching

```php
$cacheService = app(CacheService::class);

$users = $cacheService->cacheQuery(
    'users:active',
    fn() => User::where('is_active', true)->get(),
    CacheService::MEDIUM_TTL,
    ['users']
);
```

### User-Specific Caching

```php
$userProfile = $cacheService->cacheUserData(
    $userId,
    'profile',
    fn() => User::with('skills', 'reviews')->find($userId),
    CacheService::LONG_TTL
);
```

### Search Result Caching

```php
$searchResults = $cacheService->cacheSearchResults(
    'web development',
    ['category_id' => 1, 'budget_min' => 100],
    fn() => $this->performSearch($query, $filters),
    CacheService::SHORT_TTL
);
```

### API Response Caching

Apply the middleware to routes:

```php
Route::get('/api/jobs', [JobController::class, 'index'])
    ->middleware('cache.response:300'); // Cache for 5 minutes
```

## Cache Management

### Console Commands

Use the `cache:manage` command for cache operations:

```bash
# Clear all cache
php artisan cache:manage clear

# Clear specific cache store
php artisan cache:manage clear --store=redis_api

# Clear by tags
php artisan cache:manage clear --tags=jobs,skills

# Warm up cache
php artisan cache:manage warm

# Show cache statistics
php artisan cache:manage stats

# Flush specific store
php artisan cache:manage flush --store=redis_search
```

### Cache Warming

The system includes a `WarmCache` job that pre-loads frequently accessed data:

```php
// Dispatch cache warming job
WarmCache::dispatch(['categories', 'featured_jobs']);

// Or warm all cache types
WarmCache::dispatch(['all']);
```

## Cache Invalidation Strategies

### Automatic Invalidation

Models using the `CacheInvalidation` trait automatically invalidate related cache:

```php
class Job extends Model
{
    use CacheInvalidation;
    
    // Cache is automatically invalidated when job is created/updated/deleted
}
```

### Manual Invalidation

```php
// Invalidate user-specific cache
$cacheService->invalidateUserCache($userId);

// Invalidate job-specific cache
$cacheService->invalidateJobCache($jobId);

// Invalidate by tags
$cacheService->invalidateByTags(['search', 'jobs']);

// Invalidate search cache
$cacheService->invalidateSearchCache();
```

## Performance Considerations

### TTL Guidelines

- **SHORT_TTL (5 minutes)**: Frequently changing data (search results, listings)
- **MEDIUM_TTL (30 minutes)**: Semi-static data (user profiles, job details)
- **LONG_TTL (1 hour)**: Relatively static data (categories, settings)
- **VERY_LONG_TTL (24 hours)**: Static data (system configuration)

### Cache Tags

Use descriptive cache tags for efficient invalidation:

- Model-specific: `users`, `jobs`, `skills`
- Instance-specific: `user:123`, `job:456`
- Feature-specific: `search`, `api`, `sessions`

### Memory Management

Monitor Redis memory usage:

```bash
# Check cache statistics
php artisan cache:manage stats

# Clear unused cache
php artisan cache:manage clear --tags=old_data
```

## Testing

The caching system includes comprehensive tests:

```bash
# Run cache service tests
php artisan test tests/Unit/CacheServiceUnitTest.php

# Run full cache feature tests (requires Redis)
php artisan test tests/Feature/CacheServiceTest.php
```

## Monitoring

### Cache Hit Rate

Monitor cache effectiveness through hit rate metrics:

```php
$stats = $cacheService->getCacheStats();
echo "Hit Rate: " . $stats['hit_rate'] . "%";
```

### Memory Usage

Track Redis memory usage:

```php
$stats = $cacheService->getCacheStats();
echo "Memory Usage: " . $stats['memory_usage'];
```

## Best Practices

1. **Use appropriate TTL values** based on data volatility
2. **Tag cache entries** for efficient invalidation
3. **Monitor cache hit rates** to optimize caching strategies
4. **Use conditional requests** for API responses
5. **Implement graceful fallbacks** when cache is unavailable
6. **Regular cache cleanup** to prevent memory bloat
7. **Test cache invalidation** thoroughly in development

## Troubleshooting

### Common Issues

1. **Redis Connection Errors**: Check Redis server status and configuration
2. **Cache Not Invalidating**: Verify cache tags and invalidation logic
3. **Memory Issues**: Monitor Redis memory usage and implement cleanup
4. **Performance Degradation**: Check cache hit rates and optimize TTL values

### Debug Commands

```bash
# Check Redis connection
redis-cli ping

# Monitor Redis commands
redis-cli monitor

# Check cache keys
redis-cli keys "talent2income_dev_cache_*"

# Clear all cache
php artisan cache:clear
```