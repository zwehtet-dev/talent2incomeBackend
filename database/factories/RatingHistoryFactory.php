<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RatingHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RatingHistory>
 */
class RatingHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\RatingHistory>
     */
    protected $model = RatingHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $simpleAverage = $this->faker->randomFloat(2, 1.0, 5.0);
        $weightedAverage = $simpleAverage + $this->faker->randomFloat(2, -0.3, 0.3);
        $timeWeightedAverage = $weightedAverage + $this->faker->randomFloat(2, -0.2, 0.2);
        $decayedRating = max(1.0, $weightedAverage - $this->faker->randomFloat(2, 0.0, 0.5));
        $qualityScore = ($weightedAverage / 5.0) * 100 + $this->faker->randomFloat(2, -10, 10);

        $totalReviews = $this->faker->numberBetween(1, 50);

        // Generate realistic rating distribution
        $distribution = [];
        $remaining = $totalReviews;

        for ($rating = 5; $rating >= 1; $rating--) {
            if ($rating === 1) {
                $count = $remaining;
            } else {
                $maxCount = intval($remaining * 0.6);
                $count = $this->faker->numberBetween(0, $maxCount);
                $remaining -= $count;
            }

            $percentage = $totalReviews > 0 ? round(($count / $totalReviews) * 100, 1) : 0;
            $distribution[$rating] = [
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        // Generate trend data
        $trendDirection = $this->faker->randomElement(['improving', 'declining', 'stable']);
        $slope = match ($trendDirection) {
            'improving' => $this->faker->randomFloat(2, 0.2, 1.0),
            'declining' => $this->faker->randomFloat(2, -1.0, -0.2),
            'stable' => $this->faker->randomFloat(2, -0.1, 0.1),
            default => 0.0,
        };

        $trendData = [
            'direction' => $trendDirection,
            'slope' => $slope,
            'recent_average' => $simpleAverage + ($slope / 2),
            'previous_average' => $simpleAverage - ($slope / 2),
        ];

        return [
            'user_id' => User::factory(),
            'simple_average' => round($simpleAverage, 2),
            'weighted_average' => round(max(1.0, min(5.0, $weightedAverage)), 2),
            'time_weighted_average' => round(max(1.0, min(5.0, $timeWeightedAverage)), 2),
            'decayed_rating' => round(max(1.0, min(5.0, $decayedRating)), 2),
            'quality_score' => round(max(0.0, min(100.0, $qualityScore)), 2),
            'total_reviews' => $totalReviews,
            'rating_distribution' => $distribution,
            'trend_data' => $trendData,
            'calculation_trigger' => $this->faker->randomElement([
                'new_review',
                'review_updated',
                'scheduled',
                'manual',
                'background',
            ]),
        ];
    }

    /**
     * Create a rating history entry for a user with excellent ratings.
     */
    public function excellent(): static
    {
        return $this->state(function (array $attributes) {
            $totalReviews = $this->faker->numberBetween(10, 30);

            return [
                'simple_average' => $this->faker->randomFloat(2, 4.5, 5.0),
                'weighted_average' => $this->faker->randomFloat(2, 4.6, 5.0),
                'time_weighted_average' => $this->faker->randomFloat(2, 4.5, 5.0),
                'decayed_rating' => $this->faker->randomFloat(2, 4.4, 5.0),
                'quality_score' => $this->faker->randomFloat(2, 85.0, 100.0),
                'total_reviews' => $totalReviews,
                'rating_distribution' => [
                    5 => ['count' => intval($totalReviews * 0.7), 'percentage' => 70.0],
                    4 => ['count' => intval($totalReviews * 0.25), 'percentage' => 25.0],
                    3 => ['count' => intval($totalReviews * 0.05), 'percentage' => 5.0],
                    2 => ['count' => 0, 'percentage' => 0.0],
                    1 => ['count' => 0, 'percentage' => 0.0],
                ],
                'trend_data' => [
                    'direction' => 'improving',
                    'slope' => $this->faker->randomFloat(2, 0.1, 0.5),
                    'recent_average' => $this->faker->randomFloat(2, 4.7, 5.0),
                    'previous_average' => $this->faker->randomFloat(2, 4.2, 4.6),
                ],
            ];
        });
    }

    /**
     * Create a rating history entry for a user with poor ratings.
     */
    public function poor(): static
    {
        return $this->state(function (array $attributes) {
            $totalReviews = $this->faker->numberBetween(3, 15);

            return [
                'simple_average' => $this->faker->randomFloat(2, 1.0, 2.5),
                'weighted_average' => $this->faker->randomFloat(2, 1.0, 2.3),
                'time_weighted_average' => $this->faker->randomFloat(2, 1.0, 2.4),
                'decayed_rating' => $this->faker->randomFloat(2, 0.8, 2.2),
                'quality_score' => $this->faker->randomFloat(2, 10.0, 40.0),
                'total_reviews' => $totalReviews,
                'rating_distribution' => [
                    5 => ['count' => 0, 'percentage' => 0.0],
                    4 => ['count' => intval($totalReviews * 0.1), 'percentage' => 10.0],
                    3 => ['count' => intval($totalReviews * 0.2), 'percentage' => 20.0],
                    2 => ['count' => intval($totalReviews * 0.3), 'percentage' => 30.0],
                    1 => ['count' => intval($totalReviews * 0.4), 'percentage' => 40.0],
                ],
                'trend_data' => [
                    'direction' => 'declining',
                    'slope' => $this->faker->randomFloat(2, -0.8, -0.2),
                    'recent_average' => $this->faker->randomFloat(2, 1.0, 2.0),
                    'previous_average' => $this->faker->randomFloat(2, 2.0, 3.0),
                ],
            ];
        });
    }

    /**
     * Create a rating history entry triggered by a new review.
     */
    public function newReview(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'calculation_trigger' => 'new_review',
            ];
        });
    }

    /**
     * Create a rating history entry triggered by scheduled update.
     */
    public function scheduled(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'calculation_trigger' => 'scheduled',
            ];
        });
    }

    /**
     * Create a rating history entry with improving trend.
     */
    public function improving(): static
    {
        return $this->state(function (array $attributes) {
            $slope = $this->faker->randomFloat(2, 0.3, 1.0);
            $recentAverage = $this->faker->randomFloat(2, 3.5, 5.0);
            $previousAverage = $recentAverage - $slope;

            return [
                'trend_data' => [
                    'direction' => 'improving',
                    'slope' => $slope,
                    'recent_average' => round($recentAverage, 2),
                    'previous_average' => round(max(1.0, $previousAverage), 2),
                ],
            ];
        });
    }

    /**
     * Create a rating history entry with declining trend.
     */
    public function declining(): static
    {
        return $this->state(function (array $attributes) {
            $slope = $this->faker->randomFloat(2, -1.0, -0.3);
            $previousAverage = $this->faker->randomFloat(2, 3.0, 5.0);
            $recentAverage = $previousAverage + $slope;

            return [
                'trend_data' => [
                    'direction' => 'declining',
                    'slope' => $slope,
                    'recent_average' => round(max(1.0, $recentAverage), 2),
                    'previous_average' => round($previousAverage, 2),
                ],
            ];
        });
    }
}
