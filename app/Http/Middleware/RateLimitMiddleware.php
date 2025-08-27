<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request with usage analytics.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next, string $maxAttempts = '60', string $decayMinutes = '1'): Response
    {
        $key = $this->resolveRequestSignature($request);
        $analyticsKey = $this->getAnalyticsKey($request);

        // Record API usage analytics
        $this->recordUsageAnalytics($request, $analyticsKey);

        if ($this->limiter->tooManyAttempts($key, (int) $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            // Record rate limit violation
            $this->recordRateLimitViolation($request, $key, $retryAfter);

            Log::channel('auth')->warning('Rate limit exceeded', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'key' => $key,
                'retry_after' => $retryAfter,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter,
            ], 429)->header('Retry-After', $retryAfter);
        }

        $this->limiter->hit($key, (int) $decayMinutes * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            (int) $maxAttempts,
            $this->calculateRemainingAttempts($key, (int) $maxAttempts),
            $this->limiter->availableIn($key)
        );
    }

    /**
     * Resolve the request signature for rate limiting.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $route = $request->route();
        $routeName = $route ? $route->getName() : $request->path();

        // For authentication endpoints, use IP + route
        if (str_contains($request->path(), 'auth/')) {
            return 'auth:' . $request->ip() . ':' . $routeName;
        }

        // For other endpoints, include user ID if authenticated
        $userId = $request->user()?->id ?? 'guest';

        return $request->ip() . ':' . $userId . ':' . $routeName;
    }

    /**
     * Calculate the number of remaining attempts.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->limiter->attempts($key));
    }

    /**
     * Add rate limit headers to the response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts, int $retryAfter): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
        ]);

        return $response;
    }

    /**
     * Get analytics key for usage tracking.
     */
    protected function getAnalyticsKey(Request $request): string
    {
        $date = now()->format('Y-m-d');
        $hour = now()->format('H');
        $userId = auth()->id() ?? 'anonymous';
        $endpoint = $this->getEndpointCategory($request);

        return "api_usage:{$date}:{$hour}:{$userId}:{$endpoint}";
    }

    /**
     * Record API usage analytics.
     */
    protected function recordUsageAnalytics(Request $request, string $analyticsKey): void
    {
        // Increment hourly usage counter
        $currentValue = Cache::get($analyticsKey, 0) + 1;
        Cache::put($analyticsKey, $currentValue, 86400 * 7); // Keep for 7 days

        // Record daily totals
        $dailyKey = 'api_usage_daily:' . now()->format('Y-m-d');
        $dailyValue = Cache::get($dailyKey, 0) + 1;
        Cache::put($dailyKey, $dailyValue, 86400 * 30); // Keep for 30 days

        // Record endpoint-specific usage
        $endpointKey = 'api_endpoint_usage:' . now()->format('Y-m-d') . ':' . $this->getEndpointCategory($request);
        $endpointValue = Cache::get($endpointKey, 0) + 1;
        Cache::put($endpointKey, $endpointValue, 86400 * 30);

        // Record user-specific usage if authenticated
        if (auth()->check()) {
            $userKey = 'api_user_usage:' . now()->format('Y-m-d') . ':' . auth()->id();
            $userValue = Cache::get($userKey, 0) + 1;
            Cache::put($userKey, $userValue, 86400 * 30);
        }
    }

    /**
     * Record rate limit violation for analytics.
     */
    protected function recordRateLimitViolation(Request $request, string $key, int $retryAfter): void
    {
        $violationKey = 'rate_limit_violations:' . now()->format('Y-m-d');
        $violationValue = Cache::get($violationKey, 0) + 1;
        Cache::put($violationKey, $violationValue, 86400 * 30);

        // Record detailed violation data
        $violationData = [
            'timestamp' => now()->toISOString(),
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
            'endpoint' => $this->getEndpointCategory($request),
            'user_agent' => $request->userAgent(),
            'retry_after' => $retryAfter,
        ];

        $detailedKey = 'rate_limit_violation_details:' . now()->format('Y-m-d-H');
        $existing = Cache::get($detailedKey, []);
        $existing[] = $violationData;
        Cache::put($detailedKey, $existing, 86400 * 7);
    }

    /**
     * Get endpoint category for analytics.
     */
    protected function getEndpointCategory(Request $request): string
    {
        $path = $request->path();

        if (str_contains($path, 'auth/')) {
            return 'auth';
        }
        if (str_contains($path, 'jobs')) {
            return 'jobs';
        }
        if (str_contains($path, 'skills')) {
            return 'skills';
        }
        if (str_contains($path, 'messages')) {
            return 'messages';
        }
        if (str_contains($path, 'payments')) {
            return 'payments';
        }
        if (str_contains($path, 'reviews')) {
            return 'reviews';
        }
        if (str_contains($path, 'search')) {
            return 'search';
        }
        if (str_contains($path, 'admin')) {
            return 'admin';
        }
        if (str_contains($path, 'users')) {
            return 'users';
        }

        return 'other';
    }
}
