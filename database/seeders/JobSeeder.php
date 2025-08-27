<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Seeder;

class JobSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::where('is_active', true)->get();
        $clients = User::where('is_admin', false)
            ->where('is_active', true)
            ->where('email_verified_at', '!=', null)
            ->get();
        $serviceProviders = User::where('is_admin', false)
            ->where('is_active', true)
            ->whereHas('skills')
            ->get();

        if ($categories->isEmpty() || $clients->isEmpty()) {
            $this->command->warn('Categories or clients not found. Please run CategorySeeder and UserSeeder first.');

            return;
        }

        // Create open jobs (most common)
        Job::factory(40)->open()->create([
            'user_id' => $clients->random()->id,
            'category_id' => $categories->random()->id,
        ]);

        // Create in-progress jobs
        if ($serviceProviders->isNotEmpty()) {
            Job::factory(15)->inProgress()->create([
                'user_id' => $clients->random()->id,
                'category_id' => $categories->random()->id,
                'assigned_to' => $serviceProviders->random()->id,
            ]);
        }

        // Create completed jobs
        if ($serviceProviders->isNotEmpty()) {
            Job::factory(25)->completed()->create([
                'user_id' => $clients->random()->id,
                'category_id' => $categories->random()->id,
                'assigned_to' => $serviceProviders->random()->id,
            ]);
        }

        // Create cancelled jobs
        Job::factory(8)->cancelled()->create([
            'user_id' => $clients->random()->id,
            'category_id' => $categories->random()->id,
        ]);

        // Create expired jobs
        Job::factory(5)->expired()->create([
            'user_id' => $clients->random()->id,
            'category_id' => $categories->random()->id,
        ]);

        // Create urgent jobs
        Job::factory(12)->urgent()->create([
            'user_id' => $clients->random()->id,
            'category_id' => $categories->random()->id,
        ]);

        // Create high-budget jobs
        Job::factory(10)->highBudget()->create([
            'user_id' => $clients->random()->id,
            'category_id' => $categories->random()->id,
        ]);

        // Create low-budget jobs
        Job::factory(15)->lowBudget()->create([
            'user_id' => $clients->random()->id,
            'category_id' => $categories->random()->id,
        ]);

        // Create negotiable budget jobs
        Job::factory(20)->negotiable()->create([
            'user_id' => $clients->random()->id,
            'category_id' => $categories->random()->id,
        ]);

        // Create jobs with different budget types
        Job::factory(15)->hourly()->create([
            'user_id' => $clients->random()->id,
            'category_id' => $categories->random()->id,
        ]);

        Job::factory(20)->fixed()->create([
            'user_id' => $clients->random()->id,
            'category_id' => $categories->random()->id,
        ]);

        // Create jobs without deadlines
        Job::factory(10)->withoutDeadline()->create([
            'user_id' => $clients->random()->id,
            'category_id' => $categories->random()->id,
        ]);
    }
}
