<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class ApiUsageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/usage/analytics",
     *     tags={"Admin"},
     *     summary="Get API usage analytics",
     *     description="Retrieve comprehensive API usage statistics and analytics",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Date for analytics (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-01-15")
     *     ),
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Analytics period",
     *         required=false,
     *         @OA\Schema(type="string", enum={"day", "week", "month"}, example="day")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usage analytics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="total_requests", type="integer", example=15420),
     *             @OA\Property(property="unique_users", type="integer", example=342),
     *             @OA\Property(property="rate_limit_violations", type="integer", example=23),
     *             @OA\Property(
     *                 property="endpoint_usage",
     *                 type="object",
     *                 @OA\Property(property="auth", type="integer", example=2340),
     *                 @OA\Property(property="jobs", type="integer", example=5670),
     *                 @OA\Property(property="skills", type="integer", example=3210)
     *             ),
     *             @OA\Property(
     *                 property="hourly_breakdown",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="hour", type="string", example="14:00"),
     *                     @OA\Property(property="requests", type="integer", example=1250)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Forbidden")
     *         )
     *     )
     * )
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $date = $request->query('date', now()->format('Y-m-d'));
        $period = $request->query('period', 'day');

        $analytics = $this->buildAnalytics($date, $period);

        return response()->json($analytics);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/usage/rate-limits",
     *     tags={"Admin"},
     *     summary="Get rate limit violations",
     *     description="Retrieve rate limit violation statistics and details",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Date for violations (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-01-15")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rate limit data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="total_violations", type="integer", example=23),
     *             @OA\Property(
     *                 property="violations_by_endpoint",
     *                 type="object",
     *                 @OA\Property(property="auth", type="integer", example=15),
     *                 @OA\Property(property="jobs", type="integer", example=5),
     *                 @OA\Property(property="skills", type="integer", example=3)
     *             ),
     *             @OA\Property(
     *                 property="top_violators",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="ip", type="string", example="192.168.1.1"),
     *                     @OA\Property(property="violations", type="integer", example=8),
     *                     @OA\Property(property="user_id", type="integer", nullable=true, example=123)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getRateLimitViolations(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $date = $request->query('date', now()->format('Y-m-d'));

        $violations = $this->buildRateLimitAnalytics($date);

        return response()->json($violations);
    }

    /**
     * Build comprehensive analytics data.
     */
    protected function buildAnalytics(string $date, string $period): array
    {
        $analytics = [
            'total_requests' => 0,
            'unique_users' => 0,
            'rate_limit_violations' => 0,
            'endpoint_usage' => [],
            'hourly_breakdown' => [],
            'top_users' => [],
        ];

        if ($period === 'day') {
            $analytics = $this->getDailyAnalytics($date);
        } elseif ($period === 'week') {
            $analytics = $this->getWeeklyAnalytics($date);
        } elseif ($period === 'month') {
            $analytics = $this->getMonthlyAnalytics($date);
        }

        return $analytics;
    }

    /**
     * Get daily analytics.
     */
    protected function getDailyAnalytics(string $date): array
    {
        $totalRequests = Cache::get("api_usage_daily:{$date}", 0);
        $rateLimitViolations = Cache::get("rate_limit_violations:{$date}", 0);

        // Get endpoint usage
        $endpoints = ['auth', 'jobs', 'skills', 'messages', 'payments', 'reviews', 'search', 'admin', 'users'];
        $endpointUsage = [];
        foreach ($endpoints as $endpoint) {
            $endpointUsage[$endpoint] = Cache::get("api_endpoint_usage:{$date}:{$endpoint}", 0);
        }

        // Get hourly breakdown
        $hourlyBreakdown = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourKey = sprintf('%02d', $hour);
            $requests = 0;

            // Sum up requests from all users for this hour
            $pattern = "api_usage:{$date}:{$hourKey}:*";
            // Note: In a real implementation, you'd use Redis SCAN or similar
            // For now, we'll use a simplified approach

            $hourlyBreakdown[] = [
                'hour' => $hourKey . ':00',
                'requests' => $requests,
            ];
        }

        return [
            'total_requests' => $totalRequests,
            'unique_users' => $this->getUniqueUsersCount($date),
            'rate_limit_violations' => $rateLimitViolations,
            'endpoint_usage' => $endpointUsage,
            'hourly_breakdown' => $hourlyBreakdown,
            'top_users' => $this->getTopUsers($date),
        ];
    }

    /**
     * Get weekly analytics.
     */
    protected function getWeeklyAnalytics(string $date): array
    {
        $startDate = now()->parse($date)->startOfWeek();
        $weeklyData = [
            'total_requests' => 0,
            'unique_users' => 0,
            'rate_limit_violations' => 0,
            'endpoint_usage' => [],
            'daily_breakdown' => [],
        ];

        for ($i = 0; $i < 7; $i++) {
            $currentDate = $startDate->copy()->addDays($i)->format('Y-m-d');
            $dailyRequests = Cache::get("api_usage_daily:{$currentDate}", 0);
            $dailyViolations = Cache::get("rate_limit_violations:{$currentDate}", 0);

            $weeklyData['total_requests'] += $dailyRequests;
            $weeklyData['rate_limit_violations'] += $dailyViolations;

            $weeklyData['daily_breakdown'][] = [
                'date' => $currentDate,
                'requests' => $dailyRequests,
                'violations' => $dailyViolations,
            ];
        }

        return $weeklyData;
    }

    /**
     * Get monthly analytics.
     */
    protected function getMonthlyAnalytics(string $date): array
    {
        $startDate = now()->parse($date)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $monthlyData = [
            'total_requests' => 0,
            'unique_users' => 0,
            'rate_limit_violations' => 0,
            'endpoint_usage' => [],
            'daily_breakdown' => [],
        ];

        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $dailyRequests = Cache::get("api_usage_daily:{$dateStr}", 0);
            $dailyViolations = Cache::get("rate_limit_violations:{$dateStr}", 0);

            $monthlyData['total_requests'] += $dailyRequests;
            $monthlyData['rate_limit_violations'] += $dailyViolations;

            $monthlyData['daily_breakdown'][] = [
                'date' => $dateStr,
                'requests' => $dailyRequests,
                'violations' => $dailyViolations,
            ];

            $currentDate->addDay();
        }

        return $monthlyData;
    }

    /**
     * Build rate limit violation analytics.
     */
    protected function buildRateLimitAnalytics(string $date): array
    {
        $totalViolations = Cache::get("rate_limit_violations:{$date}", 0);

        // Get violations by endpoint
        $endpoints = ['auth', 'jobs', 'skills', 'messages', 'payments', 'reviews', 'search', 'admin', 'users'];
        $violationsByEndpoint = [];

        // Get detailed violation data for the day
        $violationDetails = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourKey = sprintf('%02d', $hour);
            $hourlyDetails = Cache::get("rate_limit_violation_details:{$date}-{$hourKey}", []);
            $violationDetails = array_merge($violationDetails, $hourlyDetails);
        }

        // Process violation details to get top violators and endpoint breakdown
        $ipViolations = [];
        $endpointViolations = [];

        foreach ($violationDetails as $violation) {
            $ip = $violation['ip'];
            $endpoint = $violation['endpoint'];

            $ipViolations[$ip] = ($ipViolations[$ip] ?? 0) + 1;
            $endpointViolations[$endpoint] = ($endpointViolations[$endpoint] ?? 0) + 1;
        }

        // Get top violators
        arsort($ipViolations);
        $topViolators = [];
        $count = 0;
        foreach ($ipViolations as $ip => $violations) {
            if ($count >= 10) {
                break;
            }

            // Find user_id for this IP from violation details
            $userId = null;
            foreach ($violationDetails as $violation) {
                if ($violation['ip'] === $ip && $violation['user_id']) {
                    $userId = $violation['user_id'];

                    break;
                }
            }

            $topViolators[] = [
                'ip' => $ip,
                'violations' => $violations,
                'user_id' => $userId,
            ];
            $count++;
        }

        return [
            'total_violations' => $totalViolations,
            'violations_by_endpoint' => $endpointViolations,
            'top_violators' => $topViolators,
            'violation_timeline' => $this->getViolationTimeline($date),
        ];
    }

    /**
     * Get unique users count for a date.
     */
    protected function getUniqueUsersCount(string $date): int
    {
        // This is a simplified implementation
        // In a real scenario, you'd track unique users more efficiently
        return Cache::get("unique_users:{$date}", 0);
    }

    /**
     * Get top users by API usage.
     */
    protected function getTopUsers(string $date): array
    {
        // This is a simplified implementation
        // In a real scenario, you'd have more efficient user tracking
        return [];
    }

    /**
     * Get violation timeline for a date.
     */
    protected function getViolationTimeline(string $date): array
    {
        $timeline = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourKey = sprintf('%02d', $hour);
            $hourlyDetails = Cache::get("rate_limit_violation_details:{$date}-{$hourKey}", []);

            $timeline[] = [
                'hour' => $hourKey . ':00',
                'violations' => count($hourlyDetails),
            ];
        }

        return $timeline;
    }
}
