<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\RatingHistory;
use App\Models\Review;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RatingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_get_my_stats(): void
    {
        // Create some reviews for the authenticated user
        Review::factory()->count(3)->create([
            'reviewee_id' => $this->user->id,
            'rating' => 4,
            'is_public' => true,
            'is_flagged' => false,
        ]);

        $response = $this->getJson('/api/ratings/my-stats');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'is_rating_eligible',
                ],
                'rating_stats' => [
                    'user_id',
                    'total_reviews',
                    'simple_average',
                    'weighted_average',
                    'time_weighted_average',
                    'decayed_rating',
                    'quality_score',
                    'rating_distribution',
                    'trend',
                    'last_calculated',
                ],
            ]);

        $this->assertSame(3, $response->json('rating_stats.total_reviews'));
        $this->assertSame(4.0, $response->json('rating_stats.simple_average'));
    }

    public function test_get_user_stats(): void
    {
        $targetUser = User::factory()->create();

        Review::factory()->count(5)->create([
            'reviewee_id' => $targetUser->id,
            'rating' => 5,
            'is_public' => true,
            'is_flagged' => false,
        ]);

        $response = $this->getJson("/api/ratings/user/{$targetUser->id}/stats");

        $response->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'is_rating_eligible',
                ],
                'rating_stats' => [
                    'user_id',
                    'total_reviews',
                    'simple_average',
                    'weighted_average',
                    'time_weighted_average',
                    'decayed_rating',
                    'quality_score',
                    'rating_distribution',
                    'trend',
                    'last_calculated',
                ],
            ]);

        $this->assertSame($targetUser->id, $response->json('user.id'));
        $this->assertSame(5, $response->json('rating_stats.total_reviews'));
    }

    public function test_get_user_stats_not_found(): void
    {
        $response = $this->getJson('/api/ratings/user/99999/stats');

        $response->assertNotFound()
            ->assertJson([
                'message' => 'User not found',
            ]);
    }

    public function test_get_rating_history(): void
    {
        $targetUser = User::factory()->create();

        // Create rating history entries
        RatingHistory::factory()->count(3)->create([
            'user_id' => $targetUser->id,
            'weighted_average' => 4.5,
            'quality_score' => 85.0,
        ]);

        $response = $this->getJson("/api/ratings/user/{$targetUser->id}/history");

        $response->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                ],
                'history' => [
                    '*' => [
                        'id',
                        'user_id',
                        'simple_average',
                        'weighted_average',
                        'time_weighted_average',
                        'decayed_rating',
                        'quality_score',
                        'total_reviews',
                        'rating_distribution',
                        'trend_data',
                        'calculation_trigger',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'total_entries',
            ]);

        $this->assertSame($targetUser->id, $response->json('user.id'));
        $this->assertCount(3, $response->json('history'));
    }

    public function test_get_rating_history_with_filters(): void
    {
        $targetUser = User::factory()->create();

        // Create rating history entries with different dates
        RatingHistory::factory()->create([
            'user_id' => $targetUser->id,
            'created_at' => now()->subDays(10),
        ]);

        RatingHistory::factory()->create([
            'user_id' => $targetUser->id,
            'created_at' => now()->subDays(5),
        ]);

        RatingHistory::factory()->create([
            'user_id' => $targetUser->id,
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->getJson("/api/ratings/user/{$targetUser->id}/history?" . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
            'limit' => 2,
        ]));

        $response->assertOk();
        $this->assertCount(2, $response->json('history'));
    }

    public function test_get_user_ranking(): void
    {
        $category = Category::factory()->create();

        // Create users with different ratings
        $topUser = User::factory()->create();
        $midUser = User::factory()->create();

        // Create skills for category filtering
        Skill::factory()->create([
            'user_id' => $topUser->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        Skill::factory()->create([
            'user_id' => $midUser->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        // Give users reviews and update their cache
        Review::factory()->count(5)->create([
            'reviewee_id' => $topUser->id,
            'rating' => 5,
            'is_public' => true,
            'is_flagged' => false,
        ]);

        Review::factory()->count(4)->create([
            'reviewee_id' => $midUser->id,
            'rating' => 4,
            'is_public' => true,
            'is_flagged' => false,
        ]);

        // Update cached ratings
        $topUser->update([
            'cached_weighted_rating' => 5.0,
            'cached_quality_score' => 95.0,
            'cached_total_reviews' => 5,
            'is_rating_eligible' => true,
        ]);

        $midUser->update([
            'cached_weighted_rating' => 4.0,
            'cached_quality_score' => 80.0,
            'cached_total_reviews' => 4,
            'is_rating_eligible' => true,
        ]);

        $response = $this->getJson("/api/ratings/user/{$topUser->id}/ranking?" . http_build_query([
            'category_id' => $category->id,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'is_rating_eligible',
                ],
                'ranking' => [
                    'user_id',
                    'position',
                    'total_users',
                    'percentile',
                    'quality_score',
                    'category_id',
                ],
            ]);

        $this->assertSame($topUser->id, $response->json('user.id'));
        $this->assertSame($category->id, $response->json('ranking.category_id'));
    }

    public function test_get_top_rated_users(): void
    {
        $category = Category::factory()->create();

        // Create users with different ratings
        $users = User::factory()->count(5)->create();

        foreach ($users as $index => $user) {
            // Create skills for category filtering
            Skill::factory()->create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'is_active' => true,
            ]);

            // Update cached ratings (descending order)
            $user->update([
                'cached_weighted_rating' => 5.0 - ($index * 0.5),
                'cached_quality_score' => 95.0 - ($index * 5),
                'cached_total_reviews' => 5,
                'is_rating_eligible' => true,
            ]);
        }

        $response = $this->getJson('/api/ratings/top-rated?' . http_build_query([
            'category_id' => $category->id,
            'limit' => 3,
            'min_reviews' => 3,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'users' => [
                    '*' => [
                        'id',
                        'first_name',
                        'last_name',
                        'avatar',
                        'location',
                        'cached_weighted_rating',
                        'cached_quality_score',
                        'cached_total_reviews',
                        'ranking_position',
                    ],
                ],
                'filters' => [
                    'category_id',
                    'min_reviews',
                    'limit',
                ],
            ]);

        $this->assertCount(3, $response->json('users'));
        $this->assertSame(1, $response->json('users.0.ranking_position'));
        $this->assertSame(2, $response->json('users.1.ranking_position'));
        $this->assertSame(3, $response->json('users.2.ranking_position'));

        // Verify ordering by quality score
        $users = $response->json('users');
        $this->assertGreaterThanOrEqual($users[1]['cached_quality_score'], $users[0]['cached_quality_score']);
        $this->assertGreaterThanOrEqual($users[2]['cached_quality_score'], $users[1]['cached_quality_score']);
    }

    public function test_get_rating_trends(): void
    {
        $category = Category::factory()->create();

        // Create rating history entries for trend analysis
        $users = User::factory()->count(3)->create();

        // Also add the authenticated user to the mix
        $users->push($this->user);

        foreach ($users as $user) {
            Skill::factory()->create([
                'user_id' => $user->id,
                'category_id' => $category->id,
            ]);

            RatingHistory::factory()->count(5)->create([
                'user_id' => $user->id,
                'weighted_average' => fake()->randomFloat(2, 3.0, 5.0),
                'quality_score' => fake()->randomFloat(2, 60.0, 95.0),
                'created_at' => fake()->dateTimeBetween('-1 month', 'now'),
            ]);
        }

        $response = $this->getJson('/api/ratings/trends?' . http_build_query([
            'period' => 'month',
            'category_id' => $category->id,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'trends' => [
                    'period',
                    'start_date',
                    'end_date',
                    'total_updates',
                    'average_rating_trend' => [
                        'direction',
                        'change',
                        'percentage_change',
                    ],
                    'quality_score_trend' => [
                        'direction',
                        'change',
                        'percentage_change',
                    ],
                    'daily_breakdown',
                ],
                'filters' => [
                    'period',
                    'category_id',
                ],
            ]);

        $this->assertSame('month', $response->json('trends.period'));
        $this->assertSame($category->id, $response->json('filters.category_id'));
        $this->assertGreaterThan(0, $response->json('trends.total_updates'));
    }

    public function test_validation_errors(): void
    {
        // Create a user to test with
        $testUser = User::factory()->create();

        // Test invalid category_id
        $response = $this->getJson("/api/ratings/user/{$testUser->id}/ranking?category_id=99999");
        $response->assertStatus(422);

        // Test invalid limit
        $response = $this->getJson('/api/ratings/top-rated?limit=200');
        $response->assertStatus(422);

        // Test invalid date range
        $response = $this->getJson("/api/ratings/user/{$testUser->id}/history?start_date=2024-01-01&end_date=2023-01-01");
        $response->assertStatus(422);

        // Test invalid period
        $response = $this->getJson('/api/ratings/trends?period=invalid');
        $response->assertStatus(422);
    }

    public function test_cache_parameter_works(): void
    {
        Review::factory()->count(3)->create([
            'reviewee_id' => $this->user->id,
            'rating' => 4,
            'is_public' => true,
            'is_flagged' => false,
        ]);

        // First request with cache
        $response1 = $this->getJson('/api/ratings/my-stats?use_cache=true');
        $response1->assertOk();

        // Second request without cache
        $response2 = $this->getJson('/api/ratings/my-stats?use_cache=false');
        $response2->assertOk();

        // Both should return the same data
        $this->assertSame(
            $response1->json('rating_stats.total_reviews'),
            $response2->json('rating_stats.total_reviews')
        );
    }
}
