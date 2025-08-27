<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin users
        User::factory()->admin()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@talent2income.com',
            'bio' => 'Platform administrator with full access to all features.',
            'location' => 'New York, NY',
        ]);

        User::factory()->admin()->create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@talent2income.com',
            'bio' => 'Super administrator for platform management.',
            'location' => 'San Francisco, CA',
        ]);

        // Create test users for development
        User::factory()->serviceProvider()->create([
            'first_name' => 'John',
            'last_name' => 'Developer',
            'email' => 'john@example.com',
            'bio' => 'Full-stack developer with 8+ years of experience in web development.',
            'location' => 'Austin, TX',
        ]);

        User::factory()->client()->create([
            'first_name' => 'Jane',
            'last_name' => 'Client',
            'email' => 'jane@example.com',
            'bio' => 'Small business owner looking for quality freelance services.',
            'location' => 'Los Angeles, CA',
        ]);

        // Create service providers (users who offer skills)
        User::factory(25)->serviceProvider()->create();

        // Create clients (users who post jobs)
        User::factory(20)->client()->create();

        // Create high-rated users (for realistic rating distribution)
        User::factory(10)->highRated()->create();

        // Create some regular users
        User::factory(30)->create();

        // Create some inactive users
        User::factory(5)->inactive()->create();

        // Create some unverified users
        User::factory(8)->unverified()->create();
    }
}
