<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<App\Models\SavedSearch>
     */
    protected $model = SavedSearch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['jobs', 'skills']);

        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
            'type' => $type,
            'filters' => $this->generateFilters($type),
            'sort_options' => [
                'sort_by' => $this->faker->randomElement(['relevance', 'newest', 'price', 'rating']),
                'direction' => $this->faker->randomElement(['asc', 'desc']),
            ],
            'notifications_enabled' => $this->faker->boolean(80), // 80% chance of notifications enabled
            'last_notification_sent' => $this->faker->optional(0.3)->dateTimeBetween('-1 week', 'now'),
            'notification_frequency' => $this->faker->randomElement([1, 6, 12, 24, 48, 168]), // hours
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
        ];
    }

    /**
     * Create a saved search for jobs.
     */
    public function forJobs(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'jobs',
                'filters' => $this->generateFilters('jobs'),
            ];
        });
    }

    /**
     * Create a saved search for skills.
     */
    public function forSkills(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'skills',
                'filters' => $this->generateFilters('skills'),
            ];
        });
    }

    /**
     * Create a saved search with notifications enabled.
     */
    public function withNotifications(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'notifications_enabled' => true,
                'notification_frequency' => 24, // Daily notifications
            ];
        });
    }

    /**
     * Create a saved search that needs notification check.
     */
    public function needingNotification(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'notifications_enabled' => true,
                'is_active' => true,
                'last_notification_sent' => now()->subHours(25), // Last sent 25 hours ago
                'notification_frequency' => 24, // Daily notifications
            ];
        });
    }

    /**
     * Create an inactive saved search.
     */
    public function inactive(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
            ];
        });
    }

    /**
     * Generate realistic filters based on type.
     *
     * @param string $type
     * @return array<string, mixed>
     */
    private function generateFilters(string $type): array
    {
        $baseFilters = [];

        // Add search query sometimes
        if ($this->faker->boolean(60)) {
            $baseFilters['query'] = $this->faker->words(2, true);
        }

        // Add location sometimes
        if ($this->faker->boolean(40)) {
            $baseFilters['location'] = $this->faker->city();
        }

        // Add category sometimes
        if ($this->faker->boolean(50)) {
            $baseFilters['category_id'] = $this->faker->numberBetween(1, 10);
        }

        if ($type === 'jobs') {
            return array_merge($baseFilters, $this->generateJobFilters());
        } else {
            return array_merge($baseFilters, $this->generateSkillFilters());
        }
    }

    /**
     * Generate job-specific filters.
     *
     * @return array<string, mixed>
     */
    private function generateJobFilters(): array
    {
        $filters = [];

        // Budget range
        if ($this->faker->boolean(70)) {
            $min = $this->faker->numberBetween(50, 500);
            $max = $this->faker->numberBetween($min + 100, $min + 2000);
            $filters['budget_min'] = $min;
            $filters['budget_max'] = $max;
        }

        // Urgency
        if ($this->faker->boolean(30)) {
            $filters['urgent'] = true;
        }

        // Deadline
        if ($this->faker->boolean(40)) {
            $filters['deadline'] = $this->faker->randomElement(['week', 'month', '3months']);
        }

        return $filters;
    }

    /**
     * Generate skill-specific filters.
     *
     * @return array<string, mixed>
     */
    private function generateSkillFilters(): array
    {
        $filters = [];

        // Price range
        if ($this->faker->boolean(70)) {
            $min = $this->faker->numberBetween(10, 100);
            $max = $this->faker->numberBetween($min + 20, $min + 200);
            $filters['price_min'] = $min;
            $filters['price_max'] = $max;
        }

        // Pricing type
        if ($this->faker->boolean(50)) {
            $filters['pricing_type'] = $this->faker->randomElement(['hourly', 'fixed', 'negotiable']);
        }

        // Minimum rating
        if ($this->faker->boolean(60)) {
            $filters['min_rating'] = $this->faker->randomFloat(1, 3.0, 4.5);
        }

        return $filters;
    }
}
