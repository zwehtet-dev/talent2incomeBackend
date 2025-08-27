<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RevenueAnalytics>
 */
class RevenueAnalyticsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalRevenue = $this->faker->randomFloat(2, 100, 10000);
        $platformFees = $totalRevenue * 0.1; // 10% platform fee
        $netRevenue = $totalRevenue - $platformFees;
        $transactionCount = $this->faker->numberBetween(1, 100);
        $averageTransactionValue = $transactionCount > 0 ? $totalRevenue / $transactionCount : 0;

        return [
            'date' => $this->faker->unique()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'total_revenue' => $totalRevenue,
            'platform_fees' => $platformFees,
            'net_revenue' => $netRevenue,
            'transaction_count' => $transactionCount,
            'average_transaction_value' => $averageTransactionValue,
        ];
    }
}
