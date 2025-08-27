<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Job>
 */
class JobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $budgetType = fake()->randomElement(['hourly', 'fixed', 'negotiable']);
        $hasDeadline = fake()->boolean(70);

        $jobTitles = [
            'Build a WordPress E-commerce Website',
            'Create a Mobile App for iOS and Android',
            'Design a Modern Logo and Brand Identity',
            'Write SEO-Optimized Blog Posts',
            'Manage Social Media Accounts',
            'Data Entry for Customer Database',
            'Translate Website Content to Spanish',
            'Edit Wedding Video Footage',
            'Product Photography for Online Store',
            'Virtual Assistant for Email Management',
            'Monthly Bookkeeping Services',
            'Review Legal Contract Documents',
            'Business Strategy Consultation',
            'Online Math Tutoring Sessions',
            'Podcast Audio Editing',
            'Record Professional Voice Over',
            'Create 2D Animated Explainer Video',
            'Technical SEO Website Audit',
            'Instagram Marketing Campaign',
            'Customer Support Chat Service',
        ];

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->randomElement($jobTitles),
            'description' => fake()->paragraphs(4, true),
            'budget_min' => $budgetType !== 'negotiable' ? fake()->randomFloat(2, 50, 500) : null,
            'budget_max' => $budgetType !== 'negotiable' ? fake()->randomFloat(2, 500, 2000) : null,
            'budget_type' => $budgetType,
            'deadline' => $hasDeadline ? fake()->dateTimeBetween('+1 week', '+3 months') : null,
            'status' => fake()->randomElement([
                Job::STATUS_OPEN,
                Job::STATUS_OPEN,
                Job::STATUS_OPEN, // Weight towards open jobs
                Job::STATUS_IN_PROGRESS,
                Job::STATUS_COMPLETED,
                Job::STATUS_CANCELLED,
            ]),
            'assigned_to' => null,
            'is_urgent' => fake()->boolean(15),
        ];
    }

    /**
     * Create an open job.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Job::STATUS_OPEN,
            'assigned_to' => null,
        ]);
    }

    /**
     * Create an in-progress job.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Job::STATUS_IN_PROGRESS,
            'assigned_to' => User::factory(),
        ]);
    }

    /**
     * Create a completed job.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Job::STATUS_COMPLETED,
            'assigned_to' => User::factory(),
        ]);
    }

    /**
     * Create a cancelled job.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Job::STATUS_CANCELLED,
            'assigned_to' => null,
        ]);
    }

    /**
     * Create an expired job.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Job::STATUS_EXPIRED,
            'deadline' => fake()->dateTimeBetween('-1 month', '-1 day'),
            'assigned_to' => null,
        ]);
    }

    /**
     * Create an urgent job.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_urgent' => true,
            'deadline' => fake()->dateTimeBetween('+1 day', '+1 week'),
        ]);
    }

    /**
     * Create a job with hourly budget.
     */
    public function hourly(float $minRate = 15, float $maxRate = 100): static
    {
        return $this->state(fn (array $attributes) => [
            'budget_type' => 'hourly',
            'budget_min' => fake()->randomFloat(2, $minRate, $maxRate - 10),
            'budget_max' => fake()->randomFloat(2, $maxRate - 10, $maxRate),
        ]);
    }

    /**
     * Create a job with fixed budget.
     */
    public function fixed(float $minBudget = 100, float $maxBudget = 2000): static
    {
        return $this->state(fn (array $attributes) => [
            'budget_type' => 'fixed',
            'budget_min' => fake()->randomFloat(2, $minBudget, $maxBudget - 100),
            'budget_max' => fake()->randomFloat(2, $maxBudget - 100, $maxBudget),
        ]);
    }

    /**
     * Create a job with negotiable budget.
     */
    public function negotiable(): static
    {
        return $this->state(fn (array $attributes) => [
            'budget_type' => 'negotiable',
            'budget_min' => null,
            'budget_max' => null,
        ]);
    }

    /**
     * Create a high-budget job.
     */
    public function highBudget(): static
    {
        $budgetType = fake()->randomElement(['hourly', 'fixed']);

        return $this->state(fn (array $attributes) => [
            'budget_type' => $budgetType,
            'budget_min' => $budgetType === 'hourly' ? fake()->randomFloat(2, 50, 100) : fake()->randomFloat(2, 1000, 2000),
            'budget_max' => $budgetType === 'hourly' ? fake()->randomFloat(2, 100, 200) : fake()->randomFloat(2, 2000, 5000),
        ]);
    }

    /**
     * Create a low-budget job.
     */
    public function lowBudget(): static
    {
        $budgetType = fake()->randomElement(['hourly', 'fixed']);

        return $this->state(fn (array $attributes) => [
            'budget_type' => $budgetType,
            'budget_min' => $budgetType === 'hourly' ? fake()->randomFloat(2, 10, 20) : fake()->randomFloat(2, 50, 100),
            'budget_max' => $budgetType === 'hourly' ? fake()->randomFloat(2, 20, 35) : fake()->randomFloat(2, 100, 300),
        ]);
    }

    /**
     * Create a job with a specific deadline.
     */
    public function withDeadline(string $deadline): static
    {
        return $this->state(fn (array $attributes) => [
            'deadline' => $deadline,
        ]);
    }

    /**
     * Create a job without a deadline.
     */
    public function withoutDeadline(): static
    {
        return $this->state(fn (array $attributes) => [
            'deadline' => null,
        ]);
    }

    /**
     * Create a job for a specific category.
     */
    public function forCategory(int $categoryId): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $categoryId,
        ]);
    }

    /**
     * Create a job for a specific user.
     */
    public function forUser(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    /**
     * Assign job to a specific user.
     */
    public function assignedTo(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => $userId,
            'status' => fake()->randomElement([Job::STATUS_IN_PROGRESS, Job::STATUS_COMPLETED]),
        ]);
    }
}
