<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduledReport>
 */
class ScheduledReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $frequency = $this->faker->randomElement(['daily', 'weekly', 'monthly']);
        $type = $frequency; // Type matches frequency for simplicity

        $metrics = $this->faker->randomElements([
            'revenue_analytics',
            'user_engagement',
            'cohort_analysis',
            'system_performance',
            'key_metrics',
            'trends',
            'forecasting',
        ], $this->faker->numberBetween(2, 5));

        $recipients = [];
        for ($i = 0; $i < $this->faker->numberBetween(1, 3); $i++) {
            $recipients[] = $this->faker->unique()->safeEmail();
        }

        return [
            'name' => implode(' ', $this->faker->words(3)) . ' Report',
            'type' => $type,
            'recipients' => $recipients,
            'metrics' => $metrics,
            'frequency' => $frequency,
            'last_sent_at' => $this->faker->optional(0.7)->dateTimeBetween('-7 days', 'now'),
            'next_send_at' => $this->faker->dateTimeBetween('now', '+7 days'),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
        ];
    }
}
