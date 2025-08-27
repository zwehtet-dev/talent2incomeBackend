<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Skill>
 */
class SkillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pricingType = fake()->randomElement(['hourly', 'fixed', 'negotiable']);

        $skillTitles = [
            'WordPress Website Development',
            'React.js Application Development',
            'Logo Design & Branding',
            'SEO Content Writing',
            'Social Media Management',
            'Data Entry & Processing',
            'English to Spanish Translation',
            'Video Editing & Post-Production',
            'Product Photography',
            'Virtual Assistant Services',
            'Bookkeeping & Accounting',
            'Legal Document Review',
            'Business Consulting',
            'Math Tutoring',
            'Podcast Editing',
            'Voice Over Recording',
            '2D Animation Services',
            'Technical SEO Audit',
            'Instagram Marketing',
            'Customer Support Chat',
        ];

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->randomElement($skillTitles),
            'description' => fake()->paragraphs(3, true),
            'price_per_hour' => $pricingType === 'hourly' ? fake()->randomFloat(2, 15, 150) : null,
            'price_fixed' => $pricingType === 'fixed' ? fake()->randomFloat(2, 50, 2000) : null,
            'pricing_type' => $pricingType,
            'is_available' => fake()->boolean(85),
            'is_active' => fake()->boolean(95),
        ];
    }

    /**
     * Create an hourly-priced skill.
     */
    public function hourly(float $minRate = 15, float $maxRate = 150): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_type' => 'hourly',
            'price_per_hour' => fake()->randomFloat(2, $minRate, $maxRate),
            'price_fixed' => null,
        ]);
    }

    /**
     * Create a fixed-price skill.
     */
    public function fixed(float $minPrice = 50, float $maxPrice = 2000): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_type' => 'fixed',
            'price_fixed' => fake()->randomFloat(2, $minPrice, $maxPrice),
            'price_per_hour' => null,
        ]);
    }

    /**
     * Create a negotiable-price skill.
     */
    public function negotiable(): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_type' => 'negotiable',
            'price_per_hour' => null,
            'price_fixed' => null,
        ]);
    }

    /**
     * Create an available skill.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Create an unavailable skill.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }

    /**
     * Create an inactive skill.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a premium skill (high-priced).
     */
    public function premium(): static
    {
        $pricingType = fake()->randomElement(['hourly', 'fixed']);

        return $this->state(fn (array $attributes) => [
            'pricing_type' => $pricingType,
            'price_per_hour' => $pricingType === 'hourly' ? fake()->randomFloat(2, 75, 200) : null,
            'price_fixed' => $pricingType === 'fixed' ? fake()->randomFloat(2, 1000, 5000) : null,
            'is_available' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Create a budget skill (low-priced).
     */
    public function budget(): static
    {
        $pricingType = fake()->randomElement(['hourly', 'fixed']);

        return $this->state(fn (array $attributes) => [
            'pricing_type' => $pricingType,
            'price_per_hour' => $pricingType === 'hourly' ? fake()->randomFloat(2, 10, 30) : null,
            'price_fixed' => $pricingType === 'fixed' ? fake()->randomFloat(2, 25, 200) : null,
            'is_available' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Create a skill for a specific category.
     */
    public function forCategory(int $categoryId): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $categoryId,
        ]);
    }

    /**
     * Create a skill for a specific user.
     */
    public function forUser(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }
}
