<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RatingService
{
    /**
     * Cache duration for rating calculations (in minutes).
     */
    private const CACHE_DURATION = 60;

    /**
     * Weight factors for rating calculations.
     */
    private const REVIEWER_CREDIBILITY_WEIGHT = 0.2;
    private const REVIEW_COUNT_WEIGHT = 0.5;
    private const RECENCY_WEIGHT = 0.3;
    private const ACTIVITY_DECAY_FACTOR = 0.3;

    /**
     * Calculate comprehensive rating statistics for a user.
     */
    public function calculateUserRatingStats(int $userId, bool $useCache = true): array
    {
        $cacheKey = "user_rating_stats_{$userId}";

        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $stats = $this->computeRatingStats($userId);

        if ($useCache) {
            Cache::put($cacheKey, $stats, self::CACHE_DURATION);
        }

        return $stats;
    }

    /**
     * Invalidate rating cache for a user.
     */
    public function invalidateUserCache(int $userId): void
    {
        Cache::forget("user_rating_stats_{$userId}");
        Cache::forget("reviewer_credibility_{$userId}");
    }

    /**
     * Bulk calculate and cache ratings for multiple users.
     */
    public function bulkCalculateRatings(array $userIds): array
    {
        $results = [];

        foreach ($userIds as $userId) {
            $results[$userId] = $this->calculateUserRatingStats($userId, false);
        }

        return $results;
    }

    /**
     * Get user ranking based on rating quality score.
     */
    public function getUserRanking(int $userId, int $categoryId = null): array
    {
        $cacheKey = "user_ranking_{$userId}" . ($categoryId ? "_{$categoryId}" : '');

        return Cache::remember($cacheKey, 120, function () use ($userId, $categoryId) {
            $userStats = $this->calculateUserRatingStats($userId);

            // Build query for ranking
            $query = User::select('users.id')
                ->join('reviews', 'users.id', '=', 'reviews.reviewee_id')
                ->where('reviews.is_public', true)
                ->groupBy('users.id')
                ->having(DB::raw('COUNT(reviews.id)'), '>=', 3); // Minimum 3 reviews for ranking

            if ($categoryId) {
                $query->join('skills', 'users.id', '=', 'skills.user_id')
                    ->where('skills.category_id', $categoryId);
            }

            // Get all qualified users and calculate their quality scores
            $qualifiedUsers = $query->pluck('id')->toArray();
            $rankings = [];

            foreach ($qualifiedUsers as $qualifiedUserId) {
                $stats = $this->calculateUserRatingStats($qualifiedUserId);
                $rankings[] = [
                    'user_id' => $qualifiedUserId,
                    'quality_score' => $stats['quality_score'],
                ];
            }

            // Sort by quality score
            usort($rankings, function ($a, $b) {
                return $b['quality_score'] <=> $a['quality_score'];
            });

            // Find user's position
            $userPosition = null;
            $totalUsers = count($rankings);

            foreach ($rankings as $index => $ranking) {
                if ($ranking['user_id'] === $userId) {
                    $userPosition = $index + 1;

                    break;
                }
            }

            return [
                'user_id' => $userId,
                'position' => $userPosition,
                'total_users' => $totalUsers,
                'percentile' => $userPosition && $totalUsers > 0
                    ? round((($totalUsers - $userPosition) / $totalUsers) * 100, 1)
                    : null,
                'quality_score' => $userStats['quality_score'],
                'category_id' => $categoryId,
            ];
        });
    }

    /**
     * Compute rating statistics with advanced algorithms.
     */
    private function computeRatingStats(int $userId): array
    {
        $reviews = Review::where('reviewee_id', $userId)
            ->where('is_public', true)
            ->where('is_flagged', false)
            ->with(['reviewer', 'job'])
            ->orderBy('created_at', 'desc')
            ->get();

        if ($reviews->isEmpty()) {
            return $this->getEmptyRatingStats();
        }

        $totalReviews = $reviews->count();
        $simpleAverage = $reviews->avg('rating');

        // Calculate weighted average with multiple factors
        $weightedAverage = $this->calculateWeightedAverage($reviews);

        // Calculate time-weighted average (recent reviews have more weight)
        $timeWeightedAverage = $this->calculateTimeWeightedAverage($reviews);

        // Calculate rating distribution
        $distribution = $this->calculateRatingDistribution($reviews);

        // Calculate trend analysis
        $trend = $this->calculateRatingTrend($reviews);

        // Calculate quality score (combines multiple factors)
        $qualityScore = $this->calculateQualityScore($reviews, $weightedAverage);

        // Apply activity decay if user is inactive
        $decayedRating = $this->applyActivityDecay($userId, $weightedAverage);

        return [
            'user_id' => $userId,
            'total_reviews' => $totalReviews,
            'simple_average' => round($simpleAverage, 2),
            'weighted_average' => round($weightedAverage, 2),
            'time_weighted_average' => round($timeWeightedAverage, 2),
            'decayed_rating' => round($decayedRating, 2),
            'quality_score' => round($qualityScore, 2),
            'rating_distribution' => $distribution,
            'trend' => $trend,
            'last_calculated' => now()->toISOString(),
        ];
    }

    /**
     * Calculate weighted average based on reviewer credibility and review count.
     * @param mixed $reviews
     */
    private function calculateWeightedAverage($reviews): float
    {
        $totalWeightedRating = 0;
        $totalWeight = 0;

        foreach ($reviews as $review) {
            $weight = $this->calculateReviewWeight($review);
            $totalWeightedRating += $review->rating * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $totalWeightedRating / $totalWeight : 0.0;
    }

    /**
     * Calculate individual review weight based on multiple factors.
     */
    private function calculateReviewWeight(Review $review): float
    {
        $baseWeight = 1.0;

        // Reviewer credibility weight
        $reviewerStats = $this->getReviewerCredibility($review->reviewer);
        $credibilityWeight = 1.0 + ($reviewerStats['average_rating'] - 3.0) * self::REVIEWER_CREDIBILITY_WEIGHT;
        $credibilityWeight += min($reviewerStats['review_count'] / 10, self::REVIEW_COUNT_WEIGHT);

        // Recency weight (more recent reviews have slightly higher weight)
        $daysSinceReview = $review->created_at->diffInDays(now());
        $recencyWeight = 1.0 + (1.0 / (1.0 + $daysSinceReview * 0.01)) * self::RECENCY_WEIGHT;

        // Job completion weight (reviews from completed jobs are more reliable)
        $completionWeight = $review->job && $review->job->status === 'completed' ? 1.1 : 1.0;

        return $baseWeight * $credibilityWeight * $recencyWeight * $completionWeight;
    }

    /**
     * Calculate time-weighted average (recent reviews have more impact).
     * @param mixed $reviews
     */
    private function calculateTimeWeightedAverage($reviews): float
    {
        $totalWeightedRating = 0;
        $totalWeight = 0;
        $now = now();

        foreach ($reviews as $review) {
            $daysSinceReview = $review->created_at->diffInDays($now);
            $timeWeight = 1.0 / (1.0 + $daysSinceReview * 0.02); // Decay factor

            $totalWeightedRating += $review->rating * $timeWeight;
            $totalWeight += $timeWeight;
        }

        return $totalWeight > 0 ? $totalWeightedRating / $totalWeight : 0.0;
    }

    /**
     * Calculate rating distribution.
     * @param mixed $reviews
     */
    private function calculateRatingDistribution($reviews): array
    {
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $total = $reviews->count();

        foreach ($reviews as $review) {
            $distribution[$review->rating]++;
        }

        // Convert to percentages
        foreach ($distribution as $rating => $count) {
            $distribution[$rating] = [
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }

        return $distribution;
    }

    /**
     * Calculate rating trend over time.
     * @param mixed $reviews
     */
    private function calculateRatingTrend($reviews): array
    {
        if ($reviews->count() < 2) {
            return [
                'direction' => 'stable',
                'slope' => 0,
                'recent_average' => $reviews->first()->rating ?? 0,
                'previous_average' => $reviews->first()->rating ?? 0,
            ];
        }

        // Split reviews into recent and previous periods
        $midpoint = intval($reviews->count() / 2);
        $recentReviews = $reviews->take($midpoint);
        $previousReviews = $reviews->skip($midpoint);

        $recentAverage = $recentReviews->avg('rating');
        $previousAverage = $previousReviews->avg('rating');

        $slope = $recentAverage - $previousAverage;

        $direction = 'stable';
        if ($slope > 0.2) {
            $direction = 'improving';
        } elseif ($slope < -0.2) {
            $direction = 'declining';
        }

        return [
            'direction' => $direction,
            'slope' => round($slope, 2),
            'recent_average' => round($recentAverage, 2),
            'previous_average' => round($previousAverage, 2),
        ];
    }

    /**
     * Calculate quality score based on multiple factors.
     * @param mixed $reviews
     */
    private function calculateQualityScore($reviews, float $weightedAverage): float
    {
        $reviewCount = $reviews->count();
        $consistency = $this->calculateRatingConsistency($reviews);
        $recency = $this->calculateRecencyScore($reviews);

        // Base score from weighted average (0-5 scale converted to 0-100)
        $baseScore = ($weightedAverage / 5.0) * 100;

        // Adjust for review count (more reviews = higher confidence)
        $countMultiplier = min(1.0 + ($reviewCount / 50), 1.5);

        // Adjust for consistency (consistent ratings are better)
        $consistencyMultiplier = 0.8 + ($consistency * 0.4);

        // Adjust for recency (recent activity is better)
        $recencyMultiplier = 0.9 + ($recency * 0.2);

        return $baseScore * $countMultiplier * $consistencyMultiplier * $recencyMultiplier;
    }

    /**
     * Calculate rating consistency (lower standard deviation = higher consistency).
     * @param mixed $reviews
     */
    private function calculateRatingConsistency($reviews): float
    {
        if ($reviews->count() < 2) {
            return 1.0;
        }

        $ratings = $reviews->pluck('rating')->toArray();
        $mean = array_sum($ratings) / count($ratings);
        $variance = array_sum(array_map(function ($rating) use ($mean) {
            return pow($rating - $mean, 2);
        }, $ratings)) / count($ratings);

        $standardDeviation = sqrt($variance);

        // Convert to consistency score (0-1, where 1 is most consistent)
        return max(0, 1.0 - ($standardDeviation / 2.0));
    }

    /**
     * Calculate recency score based on when the last review was received.
     * @param mixed $reviews
     */
    private function calculateRecencyScore($reviews): float
    {
        $lastReview = $reviews->first();
        $daysSinceLastReview = $lastReview->created_at->diffInDays(now());

        // Score decreases as time since last review increases
        return max(0, 1.0 - ($daysSinceLastReview / 365));
    }

    /**
     * Apply activity decay to rating based on user's recent activity.
     */
    private function applyActivityDecay(int $userId, float $rating): float
    {
        $user = User::find($userId);
        if (! $user) {
            return $rating;
        }

        // Check user's recent activity
        $daysSinceLastActivity = $this->getDaysSinceLastActivity($user);

        if ($daysSinceLastActivity <= 30) {
            return $rating; // No decay for active users
        }

        // Apply decay for inactive users
        $decayFactor = 1.0 - min(($daysSinceLastActivity - 30) / 365, 1.0) * self::ACTIVITY_DECAY_FACTOR;

        return $rating * $decayFactor;
    }

    /**
     * Get days since user's last activity.
     */
    private function getDaysSinceLastActivity(User $user): int
    {
        $lastActivity = collect([
            $user->updated_at,
            $user->jobs()->latest()->first()?->updated_at,
            $user->skills()->latest()->first()?->updated_at,
            $user->sentMessages()->latest()->first()?->created_at,
        ])->filter()->max();

        return $lastActivity ? (int) $lastActivity->diffInDays(now()) : 365;
    }

    /**
     * Get reviewer credibility metrics.
     */
    private function getReviewerCredibility(User $reviewer): array
    {
        $cacheKey = "reviewer_credibility_{$reviewer->id}";

        return Cache::remember($cacheKey, 30, function () use ($reviewer) {
            $givenReviews = $reviewer->givenReviews()->where('is_public', true)->get();
            $receivedReviews = $reviewer->receivedReviews()->where('is_public', true)->get();

            return [
                'average_rating' => $receivedReviews->avg('rating') ?? 3.0,
                'review_count' => $givenReviews->count(),
                'account_age_days' => $reviewer->created_at->diffInDays(now()),
            ];
        });
    }

    /**
     * Get empty rating stats structure.
     */
    private function getEmptyRatingStats(): array
    {
        return [
            'user_id' => 0,
            'total_reviews' => 0,
            'simple_average' => 0.0,
            'weighted_average' => 0.0,
            'time_weighted_average' => 0.0,
            'decayed_rating' => 0.0,
            'quality_score' => 0.0,
            'rating_distribution' => [
                1 => ['count' => 0, 'percentage' => 0],
                2 => ['count' => 0, 'percentage' => 0],
                3 => ['count' => 0, 'percentage' => 0],
                4 => ['count' => 0, 'percentage' => 0],
                5 => ['count' => 0, 'percentage' => 0],
            ],
            'trend' => [
                'direction' => 'stable',
                'slope' => 0,
                'recent_average' => 0,
                'previous_average' => 0,
            ],
            'last_calculated' => now()->toISOString(),
        ];
    }
}
