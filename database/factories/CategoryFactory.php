<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = implode(' ', fake()->words(2)) . ' ' . fake()->randomElement(['Services', 'Solutions', 'Work', 'Tasks']);

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->paragraph(2),
            'icon' => fake()->randomElement([
                'code', 'mobile', 'palette', 'edit', 'megaphone',
                'keyboard', 'language', 'video', 'camera', 'user-tie',
                'calculator', 'balance-scale', 'lightbulb', 'graduation-cap',
                'music', 'microphone', 'play', 'search', 'share-alt', 'headset',
            ]),
            'parent_id' => null,
            'lft' => 1,
            'rgt' => 2,
            'depth' => 0,
            'is_active' => fake()->boolean(90),
        ];
    }

    /**
     * Create a root category.
     */
    public function root(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => null,
            'depth' => 0,
            'lft' => 1,
            'rgt' => 2,
            'is_active' => true,
        ]);
    }

    /**
     * Create a child category.
     */
    public function child(int $parentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
            'depth' => 1,
            'lft' => 1,
            'rgt' => 2,
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive category.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a popular category (for seeding with more jobs/skills).
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'description' => fake()->paragraph(3),
        ]);
    }

    /**
     * Create predefined main categories.
     */
    public function predefined(string $name, string $icon): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => $icon,
            'is_active' => true,
            'parent_id' => null,
            'depth' => 0,
            'lft' => 1,
            'rgt' => 2,
        ]);
    }
}
