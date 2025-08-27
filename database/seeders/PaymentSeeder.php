<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Job;
use App\Models\Payment;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get jobs that should have payments (in_progress and completed)
        $jobsWithPayments = Job::whereIn('status', ['in_progress', 'completed'])
            ->whereNotNull('assigned_to')
            ->get();

        if ($jobsWithPayments->isEmpty()) {
            $this->command->warn('No jobs with assigned users found. Please run JobSeeder first.');

            return;
        }

        foreach ($jobsWithPayments as $job) {
            // Determine payment amount based on job budget
            $amount = (float) ($job->budget_max ?? $job->budget_min ?? fake()->randomFloat(2, 100, 1000));

            // Create payment based on job status
            if ($job->status === 'completed') {
                // Completed jobs should have released payments
                Payment::factory()
                    ->released()
                    ->withAmount($amount)
                    ->create([
                        'job_id' => $job->id,
                        'payer_id' => $job->user_id,
                        'payee_id' => $job->assigned_to,
                    ]);
            } else {
                // In-progress jobs should have held payments
                Payment::factory()
                    ->held()
                    ->withAmount($amount)
                    ->create([
                        'job_id' => $job->id,
                        'payer_id' => $job->user_id,
                        'payee_id' => $job->assigned_to,
                    ]);
            }
        }

        // Create some additional payments with various statuses

        // Pending payments
        Payment::factory(10)->pending()->create();

        // Failed payments
        Payment::factory(8)->failed()->create();

        // Refunded payments
        Payment::factory(5)->refunded()->create();

        // Disputed payments
        Payment::factory(3)->disputed()->create();

        // Payments with different payment methods
        Payment::factory(15)->stripeCard()->create();
        Payment::factory(10)->paypal()->create();
        Payment::factory(8)->bankTransfer()->create();
        Payment::factory(5)->stripeBank()->create();

        // Small and large payments
        Payment::factory(20)->small()->create();
        Payment::factory(10)->large()->create();

        // Recent and old payments
        Payment::factory(15)->recent()->create();
        Payment::factory(12)->old()->create();

        // Successful payment flows
        Payment::factory(25)->successfulFlow()->create();

        // Failed payment flows
        Payment::factory(8)->failedFlow()->create();

        // Payments with custom fee percentages (for testing)
        Payment::factory(5)->withFeePercentage(500.00, 3.0)->create(); // 3% fee
        Payment::factory(5)->withFeePercentage(1000.00, 7.0)->create(); // 7% fee
    }
}
