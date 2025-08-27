<?php

namespace App\Http\Middleware;

use App\Services\CacheService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class CacheResponseMiddleware
{
    protected CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $ttl = 300): Response
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Skip caching for authenticated admin requests
        if ($request->user()?->is_admin) {
            return $next($request);
        }

        $cacheKey = $this->buildCacheKey($request);

        // Check for conditional request headers
        $etag = Cache::store('redis_api')->get($cacheKey . ':etag');
        if ($etag && $request->header('If-None-Match') === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', "public, max-age={$ttl}");
        }

        $lastModified = Cache::store('redis_api')->get($cacheKey . ':last_modified');
        if ($lastModified && $request->header('If-Modified-Since') === $lastModified) {
            return response('', 304)
                ->header('Last-Modified', $lastModified)
                ->header('Cache-Control', "public, max-age={$ttl}");
        }

        // Check if response is cached
        $cachedResponse = Cache::store('redis_api')->get($cacheKey);
        if ($cachedResponse) {
            $response = response($cachedResponse['content'], $cachedResponse['status'])
                ->withHeaders($cachedResponse['headers']);

            // Add cache headers
            $response->header('X-Cache', 'HIT');
            $response->header('Cache-Control', "public, max-age={$ttl}");

            if (isset($cachedResponse['etag'])) {
                $response->header('ETag', $cachedResponse['etag']);
            }

            if (isset($cachedResponse['last_modified'])) {
                $response->header('Last-Modified', $cachedResponse['last_modified']);
            }

            return $response;
        }

        // Process request
        $response = $next($request);

        // Only cache successful responses
        if ($response->getStatusCode() === 200) {
            $this->cacheResponse($cacheKey, $response, $ttl);
        }

        $response->header('X-Cache', 'MISS');
        $response->header('Cache-Control', "public, max-age={$ttl}");

        return $response;
    }

    /**
     * Build cache key from request
     */
    private function buildCacheKey(Request $request): string
    {
        $uri = $request->getRequestUri();
        $params = $request->query();
        ksort($params);

        $key = 'api:' . md5($uri . serialize($params));

        // Include user context for personalized responses
        if ($request->user()) {
            $key .= ':user:' . $request->user()->id;
        }

        return $key;
    }

    /**
     * Cache the response
     */
    private function cacheResponse(string $cacheKey, Response $response, int $ttl): void
    {
        $content = $response->getContent();
        $etag = md5($content);
        $lastModified = now()->toRfc7231String();

        $cacheData = [
            'content' => $content,
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'etag' => $etag,
            'last_modified' => $lastModified,
        ];

        Cache::store('redis_api')->put($cacheKey, $cacheData, $ttl);
        Cache::store('redis_api')->put($cacheKey . ':etag', $etag, $ttl);
        Cache::store('redis_api')->put($cacheKey . ':last_modified', $lastModified, $ttl);

        // Add response headers
        $response->header('ETag', $etag);
        $response->header('Last-Modified', $lastModified);
    }
}
