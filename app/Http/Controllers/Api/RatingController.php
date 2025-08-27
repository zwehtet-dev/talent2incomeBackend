<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RatingHistory;
use App\Models\User;
use App\Services\RatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RatingController extends Controller
{
    /**
     * Rating service instance.
     */
    private RatingService $ratingService;

    /**
     * Create a new controller instance.
     */
    public function __construct(RatingService $ratingService)
    {
        $this->ratingService = $ratingService;
    }

    /**
     * Get comprehensive rating statistics for a user.
     */
    public function getUserStats(Request $request, int $userId): JsonResponse
    {
        $user = User::find($userId);

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $useCache = $request->boolean('use_cache', true);
        $stats = $this->ratingService->calculateUserRatingStats($userId, $useCache);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'is_rating_eligible' => $user->isRatingEligible(),
            ],
            'rating_stats' => $stats,
        ]);
    }

    /**
     * Get current user's rating statistics.
     */
    public function getMyStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $useCache = $request->boolean('use_cache', true);

        $stats = $this->ratingService->calculateUserRatingStats($user->id, $useCache);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'is_rating_eligible' => $user->isRatingEligible(),
            ],
            'rating_stats' => $stats,
        ]);
    }

    /**
     * Get user's rating history.
     */
    public function getRatingHistory(Request $request, int $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:100',
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::find($userId);

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $query = RatingHistory::where('user_id', $userId)->orderBy('created_at', 'desc');

        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }

        $limit = $request->integer('limit', 50);
        $history = $query->limit($limit)->get();

        // Add calculated fields
        $history->each(function ($entry) {
            $entry->rating_change = $entry->rating_change;
            $entry->quality_score_change = $entry->quality_score_change;
            $entry->trend_direction = $entry->getTrendDirection();
        });

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
            ],
            'history' => $history,
            'total_entries' => RatingHistory::where('user_id', $userId)->count(),
        ]);
    }

    /**
     * Get user's ranking in category or overall.
     */
    public function getUserRanking(Request $request, int $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'integer|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::find($userId);

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $categoryId = $request->integer('category_id');
        $ranking = $this->ratingService->getUserRanking($userId, $categoryId);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'is_rating_eligible' => $user->isRatingEligible(),
            ],
            'ranking' => $ranking,
        ]);
    }

    /**
     * Get top-rated users.
     */
    public function getTopRatedUsers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'integer|exists:categories,id',
            'limit' => 'integer|min:1|max:100',
            'min_reviews' => 'integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $limit = $request->integer('limit', 20);
        $minReviews = $request->integer('min_reviews', 3);
        $categoryId = $request->integer('category_id');

        $query = User::ratingEligible()
            ->where('cached_total_reviews', '>=', $minReviews)
            ->orderByQuality('desc');

        if ($categoryId) {
            $query->whereHas('skills', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId)->where('is_active', true);
            });
        }

        $users = $query->limit($limit)
            ->select([
                'id', 'first_name', 'last_name', 'avatar', 'location',
                'cached_weighted_rating', 'cached_quality_score', 'cached_total_reviews',
            ])
            ->get();

        // Add ranking position
        $users->each(function ($user, $index) {
            $user->ranking_position = $index + 1;
        });

        return response()->json([
            'users' => $users,
            'filters' => [
                'category_id' => $categoryId,
                'min_reviews' => $minReviews,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * Get rating trends and analytics.
     */
    public function getRatingTrends(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'in:week,month,quarter,year',
            'category_id' => 'integer|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $period = $request->string('period', 'month');
        $categoryId = $request->integer('category_id');

        $startDate = match ($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };

        // Get rating history for the period
        $historyQuery = RatingHistory::where('created_at', '>=', $startDate)
            ->orderBy('created_at', 'asc');

        if ($categoryId) {
            $historyQuery->whereHas('user.skills', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        $history = $historyQuery->get();

        // Calculate trends
        $trends = [
            'period' => $period,
            'start_date' => $startDate->toDateString(),
            'end_date' => now()->toDateString(),
            'total_updates' => $history->count(),
            'average_rating_trend' => $this->calculateTrend($history, 'weighted_average'),
            'quality_score_trend' => $this->calculateTrend($history, 'quality_score'),
            'daily_breakdown' => $this->getDailyBreakdown($history),
        ];

        return response()->json([
            'trends' => $trends,
            'filters' => [
                'period' => $period,
                'category_id' => $categoryId,
            ],
        ]);
    }

    /**
     * Calculate trend for a specific metric.
     * @param mixed $history
     */
    private function calculateTrend($history, string $metric): array
    {
        if ($history->isEmpty()) {
            return [
                'direction' => 'stable',
                'change' => 0,
                'percentage_change' => 0,
            ];
        }

        $first = $history->first()->{$metric};
        $last = $history->last()->{$metric};
        $change = $last - $first;
        $percentageChange = $first > 0 ? ($change / $first) * 100 : 0;

        $direction = 'stable';
        if ($change > 0.1) {
            $direction = 'improving';
        } elseif ($change < -0.1) {
            $direction = 'declining';
        }

        return [
            'direction' => $direction,
            'change' => round($change, 2),
            'percentage_change' => round($percentageChange, 2),
        ];
    }

    /**
     * Get daily breakdown of rating updates.
     * @param mixed $history
     */
    private function getDailyBreakdown($history): array
    {
        return $history->groupBy(function ($item) {
            return $item->created_at->format('Y-m-d');
        })->map(function ($dayHistory) {
            return [
                'count' => $dayHistory->count(),
                'avg_weighted_rating' => round($dayHistory->avg('weighted_average'), 2),
                'avg_quality_score' => round($dayHistory->avg('quality_score'), 2),
            ];
        })->toArray();
    }
}
