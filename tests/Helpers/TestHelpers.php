<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

class TestHelpers
{
    use WithFaker;

    /**
     * Create a test user with optional attributes.
     */
    public static function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * Create an admin user.
     */
    public static function createAdminUser(array $attributes = []): User
    {
        return self::createUser(array_merge(['is_admin' => true], $attributes));
    }

    /**
     * Create a verified user.
     */
    public static function createVerifiedUser(array $attributes = []): User
    {
        return self::createUser(array_merge([
            'email_verified_at' => now(),
        ], $attributes));
    }

    /**
     * Create multiple users.
     */
    public static function createUsers(int $count, array $attributes = []): \Illuminate\Database\Eloquent\Collection
    {
        return User::factory()->count($count)->create($attributes);
    }

    /**
     * Authenticate a user for API testing.
     */
    public static function authenticateUser(User $user = null): User
    {
        $user = $user ?: self::createUser();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Authenticate an admin user for API testing.
     */
    public static function authenticateAdmin(User $admin = null): User
    {
        $admin = $admin ?: self::createAdminUser();
        Sanctum::actingAs($admin);

        return $admin;
    }

    /**
     * Generate a valid password.
     */
    public static function validPassword(): string
    {
        return 'Password123!';
    }

    /**
     * Generate user registration data.
     */
    public static function userRegistrationData(array $overrides = []): array
    {
        $password = self::validPassword();

        return array_merge([
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => $password,
            'password_confirmation' => $password,
        ], $overrides);
    }

    /**
     * Generate user login data.
     */
    public static function userLoginData(User $user = null, string $password = null): array
    {
        $user = $user ?: self::createUser();
        $password = $password ?: self::validPassword();

        return [
            'email' => $user->email,
            'password' => $password,
        ];
    }

    /**
     * Generate job creation data.
     */
    public static function jobCreationData(array $overrides = []): array
    {
        return array_merge([
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(3),
            'category_id' => 1, // Assuming category exists
            'budget_min' => fake()->numberBetween(100, 500),
            'budget_max' => fake()->numberBetween(600, 1000),
            'budget_type' => fake()->randomElement(['hourly', 'fixed', 'negotiable']),
            'deadline' => fake()->dateTimeBetween('+1 week', '+1 month')->format('Y-m-d'),
            'is_urgent' => fake()->boolean(20), // 20% chance of being urgent
        ], $overrides);
    }

    /**
     * Generate skill creation data.
     */
    public static function skillCreationData(array $overrides = []): array
    {
        $pricingType = fake()->randomElement(['hourly', 'fixed', 'negotiable']);

        $data = [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(2),
            'category_id' => 1, // Assuming category exists
            'pricing_type' => $pricingType,
        ];

        // Add pricing based on type
        if ($pricingType === 'hourly') {
            $data['price_per_hour'] = fake()->numberBetween(25, 150);
        } elseif ($pricingType === 'fixed') {
            $data['price_fixed'] = fake()->numberBetween(100, 1000);
        }

        return array_merge($data, $overrides);
    }

    /**
     * Generate message data.
     */
    public static function messageData(User $recipient, array $overrides = []): array
    {
        return array_merge([
            'recipient_id' => $recipient->id,
            'content' => fake()->paragraph(2),
        ], $overrides);
    }

    /**
     * Generate review data.
     */
    public static function reviewData(int $jobId, User $reviewee, array $overrides = []): array
    {
        return array_merge([
            'job_id' => $jobId,
            'reviewee_id' => $reviewee->id,
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->paragraph(1),
        ], $overrides);
    }

    /**
     * Assert API response structure.
     */
    public static function assertApiResponseStructure(array $structure): array
    {
        return $structure;
    }

    /**
     * Assert pagination structure.
     */
    public static function assertPaginationStructure(): array
    {
        return [
            'data' => [],
            'meta' => [
                'current_page',
                'total',
                'per_page',
                'last_page',
            ],
        ];
    }

    /**
     * Assert validation error structure.
     */
    public static function assertValidationErrorStructure(): array
    {
        return [
            'message',
            'errors',
        ];
    }
}
