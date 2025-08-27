<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserEngagementAnalytics>
 */
class UserEngagementAnalyticsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dailyActiveUsers = $this->faker->numberBetween(10, 500);
        $weeklyActiveUsers = $dailyActiveUsers + $this->faker->numberBetween(50, 200);
        $monthlyActiveUsers = $weeklyActiveUsers + $this->faker->numberBetween(100, 500);

        return [
            'date' => $this->faker->unique()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'daily_active_users' => $dailyActiveUsers,
            'weekly_active_users' => $weeklyActiveUsers,
            'monthly_active_users' => $monthlyActiveUsers,
            'new_registrations' => $this->faker->numberBetween(0, 50),
            'jobs_posted' => $this->faker->numberBetween(0, 100),
            'skills_posted' => $this->faker->numberBetween(0, 80),
            'messages_sent' => $this->faker->numberBetween(0, 500),
            'reviews_created' => $this->faker->numberBetween(0, 30),
            'average_session_duration' => $this->faker->randomFloat(2, 5, 120), // 5-120 minutes
        ];
    }
}
