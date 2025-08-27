<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');

        // Seed in order of dependencies
        $this->call([
            CategorySeeder::class,
            UserSeeder::class,
            SkillSeeder::class,
            JobSeeder::class,
            MessageSeeder::class,
            PaymentSeeder::class,
            ReviewSeeder::class,
        ]);

        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->info('');
        $this->command->info('📊 Seeded data summary:');
        $this->command->info('   - Categories: Main categories with subcategories');
        $this->command->info('   - Users: Admins, service providers, clients, and test users');
        $this->command->info('   - Skills: Various pricing models and availability states');
        $this->command->info('   - Jobs: Different statuses, budgets, and deadlines');
        $this->command->info('   - Messages: Conversation threads and standalone messages');
        $this->command->info('   - Payments: Various payment states and methods');
        $this->command->info('   - Reviews: Realistic rating distribution and comments');
        $this->command->info('');
        $this->command->info('🔑 Test accounts:');
        $this->command->info('   - Admin: admin@talent2income.com (password: password)');
        $this->command->info('   - Developer: john@example.com (password: password)');
        $this->command->info('   - Client: jane@example.com (password: password)');
    }
}
