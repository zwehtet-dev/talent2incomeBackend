<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = '12345678';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => fake()->boolean(80) ? now() : null,
            'password' => static::$password ??= Hash::make('password'),
            'avatar' => fake()->boolean(60) ? fake()->imageUrl(200, 200, 'people') : null,
            'bio' => fake()->boolean(70) ? fake()->paragraph(2) : null,
            'location' => fake()->boolean(80) ? fake()->city() : null,
            'phone' => fake()->boolean(60) ? fake()->phoneNumber() : null,
            'is_active' => fake()->boolean(95),
            'is_admin' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create an admin user.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive user.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a service provider (user with skills).
     */
    public function serviceProvider(): static
    {
        return $this->state(fn (array $attributes) => [
            'bio' => fake()->paragraph(3),
            'location' => fake()->city(),
            'phone' => fake()->phoneNumber(),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * Create a client (user who posts jobs).
     */
    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'bio' => fake()->boolean(50) ? fake()->paragraph(1) : null,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * Create a user with high rating potential.
     */
    public function highRated(): static
    {
        return $this->state(fn (array $attributes) => [
            'bio' => fake()->paragraph(3),
            'location' => fake()->city(),
            'phone' => fake()->phoneNumber(),
            'avatar' => fake()->imageUrl(200, 200, 'people'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }
}
