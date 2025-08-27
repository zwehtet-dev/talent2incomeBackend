<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Job;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::where('is_active', true)
            ->where('email_verified_at', '!=', null)
            ->get();
        $jobs = Job::whereIn('status', ['open', 'in_progress', 'completed'])->get();

        if ($users->count() < 2) {
            $this->command->warn('Not enough users found. Please run UserSeeder first.');

            return;
        }

        // Create conversations between random users
        for ($i = 0; $i < 30; $i++) {
            $user1 = $users->random();
            $user2 = $users->where('id', '!=', $user1->id)->random();
            $job = $jobs->isNotEmpty() ? $jobs->random() : null;

            // Create a conversation thread (3-8 messages)
            $messageCount = fake()->numberBetween(3, 8);
            $currentSender = $user1;
            $currentRecipient = $user2;

            for ($j = 0; $j < $messageCount; $j++) {
                $messageType = fake()->randomElement(['inquiry', 'update', 'general']);

                switch ($messageType) {
                    case 'inquiry':
                        Message::factory()
                            ->projectInquiry()
                            ->between($currentSender->id, $currentRecipient->id)
                            ->create([
                                'job_id' => $job?->id,
                                'created_at' => now()->subMinutes(($messageCount - $j) * 30),
                            ]);

                        break;

                    case 'update':
                        Message::factory()
                            ->projectUpdate()
                            ->between($currentSender->id, $currentRecipient->id)
                            ->create([
                                'job_id' => $job?->id,
                                'created_at' => now()->subMinutes(($messageCount - $j) * 30),
                            ]);

                        break;

                    default:
                        Message::factory()
                            ->between($currentSender->id, $currentRecipient->id)
                            ->create([
                                'job_id' => $job?->id,
                                'created_at' => now()->subMinutes(($messageCount - $j) * 30),
                                'is_read' => $j < $messageCount - 2, // Last 2 messages unread
                            ]);

                        break;
                }

                // Alternate sender and recipient
                [$currentSender, $currentRecipient] = [$currentRecipient, $currentSender];
            }
        }

        // Create some standalone messages
        Message::factory(50)->create([
            'sender_id' => $users->random()->id,
            'recipient_id' => $users->random()->id,
            'job_id' => $jobs->isNotEmpty() ? $jobs->random()->id : null,
        ]);

        // Create some messages without job context
        Message::factory(25)->withoutJob()->create([
            'sender_id' => $users->random()->id,
            'recipient_id' => $users->random()->id,
        ]);

        // Create some unread messages
        Message::factory(30)->unread()->create([
            'sender_id' => $users->random()->id,
            'recipient_id' => $users->random()->id,
            'job_id' => $jobs->isNotEmpty() ? $jobs->random()->id : null,
        ]);

        // Create some recent messages
        Message::factory(20)->recent()->create([
            'sender_id' => $users->random()->id,
            'recipient_id' => $users->random()->id,
            'job_id' => $jobs->isNotEmpty() ? $jobs->random()->id : null,
        ]);

        // Create some old messages
        Message::factory(15)->old()->create([
            'sender_id' => $users->random()->id,
            'recipient_id' => $users->random()->id,
            'job_id' => $jobs->isNotEmpty() ? $jobs->random()->id : null,
        ]);
    }
}
