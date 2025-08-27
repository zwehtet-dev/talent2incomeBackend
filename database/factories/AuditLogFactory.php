<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventTypes = [
            'auth.login_success',
            'auth.login_failed',
            'auth.logout',
            'model.created',
            'model.updated',
            'model.deleted',
            'api.jobs.read',
            'api.users.update',
            'admin.user_suspended',
        ];

        $severities = ['info', 'warning', 'error', 'critical'];

        return [
            'event_type' => $this->faker->randomElement($eventTypes),
            'auditable_type' => $this->faker->randomElement([
                'App\Models\User',
                'App\Models\Job',
                'App\Models\Skill',
                'App\Models\Payment',
            ]),
            'auditable_id' => $this->faker->numberBetween(1, 100),
            'user_id' => User::factory(),
            'user_type' => 'App\Models\User',
            'old_values' => $this->faker->optional()->randomElement([
                ['name' => 'old_name', 'email' => 'old@example.com'],
                ['status' => 'pending'],
                null,
            ]),
            'new_values' => $this->faker->optional()->randomElement([
                ['name' => 'new_name', 'email' => 'new@example.com'],
                ['status' => 'completed'],
                null,
            ]),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'url' => $this->faker->url(),
            'http_method' => $this->faker->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
            'request_data' => $this->faker->optional()->randomElement([
                ['param1' => 'value1', 'param2' => 'value2'],
                null,
            ]),
            'session_id' => $this->faker->uuid(),
            'transaction_id' => $this->faker->uuid(),
            'description' => $this->faker->sentence(),
            'metadata' => $this->faker->optional()->randomElement([
                ['additional_info' => 'test', 'context' => 'factory'],
                null,
            ]),
            'severity' => $this->faker->randomElement($severities),
            'is_sensitive' => $this->faker->boolean(20), // 20% chance of being sensitive
            'hash' => hash('sha256', $this->faker->text()),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Create a sensitive audit log.
     */
    public function sensitive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sensitive' => true,
            'event_type' => $this->faker->randomElement([
                'auth.login_failed',
                'payment.created',
                'gdpr.request_created',
                'security.incident_detected',
            ]),
        ]);
    }

    /**
     * Create a critical severity audit log.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'critical',
            'event_type' => $this->faker->randomElement([
                'security.breach_detected',
                'system.failure',
                'data.corruption',
            ]),
        ]);
    }

    /**
     * Create an old audit log.
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-10 years', '-8 years'),
        ]);
    }
}
