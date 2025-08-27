<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::where('is_active', true)->get();
        $serviceProviders = User::where('is_admin', false)
            ->where('is_active', true)
            ->where('email_verified_at', '!=', null)
            ->get();

        if ($categories->isEmpty() || $serviceProviders->isEmpty()) {
            $this->command->warn('Categories or service providers not found. Please run CategorySeeder and UserSeeder first.');

            return;
        }

        // Create skills for each service provider
        foreach ($serviceProviders as $user) {
            $skillCount = fake()->numberBetween(1, 4); // Each user has 1-4 skills

            for ($i = 0; $i < $skillCount; $i++) {
                $category = $categories->random();

                // Create different types of skills
                $skillType = fake()->randomElement(['hourly', 'fixed', 'negotiable', 'premium', 'budget']);

                switch ($skillType) {
                    case 'hourly':
                        Skill::factory()
                            ->hourly(20, 100)
                            ->forUser($user->id)
                            ->forCategory($category->id)
                            ->create();

                        break;

                    case 'fixed':
                        Skill::factory()
                            ->fixed(100, 1500)
                            ->forUser($user->id)
                            ->forCategory($category->id)
                            ->create();

                        break;

                    case 'negotiable':
                        Skill::factory()
                            ->negotiable()
                            ->forUser($user->id)
                            ->forCategory($category->id)
                            ->create();

                        break;

                    case 'premium':
                        Skill::factory()
                            ->premium()
                            ->forUser($user->id)
                            ->forCategory($category->id)
                            ->create();

                        break;

                    case 'budget':
                        Skill::factory()
                            ->budget()
                            ->forUser($user->id)
                            ->forCategory($category->id)
                            ->create();

                        break;
                }
            }
        }

        // Create some unavailable skills
        Skill::factory(15)->unavailable()->create([
            'user_id' => $serviceProviders->random()->id,
            'category_id' => $categories->random()->id,
        ]);

        // Create some inactive skills
        Skill::factory(10)->inactive()->create([
            'user_id' => $serviceProviders->random()->id,
            'category_id' => $categories->random()->id,
        ]);

        // Create additional random skills
        Skill::factory(50)->create([
            'user_id' => $serviceProviders->random()->id,
            'category_id' => $categories->random()->id,
        ]);
    }
}
