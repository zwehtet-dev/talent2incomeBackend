<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SystemPerformanceMetrics>
 */
class SystemPerformanceMetricsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalRequests = $this->faker->numberBetween(100, 10000);
        $errorCount = $this->faker->numberBetween(0, (int)($totalRequests * 0.05)); // Max 5% errors
        $errorRate = $totalRequests > 0 ? ($errorCount / $totalRequests) * 100 : 0;

        return [
            'recorded_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
            'average_response_time' => $this->faker->randomFloat(2, 50, 2000), // 50ms to 2s
            'total_requests' => $totalRequests,
            'error_count' => $errorCount,
            'error_rate' => $errorRate,
            'cpu_usage' => $this->faker->randomFloat(2, 10, 95), // 10% to 95%
            'memory_usage' => $this->faker->randomFloat(2, 20, 90), // 20% to 90%
            'disk_usage' => $this->faker->randomFloat(2, 15, 85), // 15% to 85%
            'active_connections' => $this->faker->numberBetween(5, 200),
        ];
    }
}
