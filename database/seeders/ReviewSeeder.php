<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Job;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get completed jobs for creating reviews
        $completedJobs = Job::where('status', 'completed')
            ->whereNotNull('assigned_to')
            ->with(['user', 'assignedUser'])
            ->get();

        if ($completedJobs->isEmpty()) {
            $this->command->warn('No completed jobs found. Please run JobSeeder first.');

            return;
        }

        foreach ($completedJobs as $job) {
            // Ensure valid user and assigned user for reviews
            if ($job->user_id && $job->assigned_to) {
                // Create review from client to service provider (80% chance)
                if (fake()->boolean(80)) {
                    $this->createReview($job, $job->user_id, $job->assigned_to);
                }

                // Create review from service provider to client (60% chance)
                if (fake()->boolean(60)) {
                    $this->createReview($job, $job->assigned_to, $job->user_id);
                }
            }
        }

        // Create additional reviews for variety
        for ($i = 0; $i < 50; $i++) {
            $job = $completedJobs->random();
            $reviewer = User::inRandomOrder()->first();
            $reviewee = User::where('id', '!=', $reviewer->id)->inRandomOrder()->first();

            if ($reviewer && $reviewee) {
                $this->createReview($job, $reviewer->id, $reviewee->id);
            }
        }

        // Create some additional reviews with different characteristics
        for ($i = 0; $i < 30; $i++) {
            $job = $completedJobs->random();
            $reviewer = User::inRandomOrder()->first();
            $reviewee = User::where('id', '!=', $reviewer->id)->inRandomOrder()->first();

            if ($reviewer && $reviewee) {
                // Choose review type randomly
                $reviewType = fake()->randomElement(['positive', 'neutral', 'negative', 'detailed', 'withoutComment']);
                $this->createReview($job, $reviewer->id, $reviewee->id, $reviewType);
            }
        }
    }

    /**
     * Create a review for a job.
     *
     * @param Job $job
     * @param int $reviewerId
     * @param int $revieweeId
     * @param string $reviewType
     */
    protected function createReview(Job $job, int $reviewerId, int $revieweeId, string $reviewType = 'positive')
    {
        try {
            $factory = Review::factory();

            switch ($reviewType) {
                case 'positive':
                    $factory = $factory->positive();

                    break;
                case 'neutral':
                    $factory = $factory->neutral();

                    break;
                case 'negative':
                    $factory = $factory->negative();

                    break;
                case 'detailed':
                    $factory = $factory->detailed();

                    break;
                case 'withoutComment':
                    $factory = $factory->withoutComment();

                    break;
            }

            $factory->create([
                'job_id' => $job->id,
                'reviewer_id' => $reviewerId,
                'reviewee_id' => $revieweeId,
            ]);
        } catch (\Exception $e) {
            // Skip if duplicate or any error
        }
    }
}
