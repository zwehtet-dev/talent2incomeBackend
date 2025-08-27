<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Job;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 50, 2000);
        $platformFee = Payment::calculatePlatformFee($amount);

        return [
            'job_id' => Job::factory(),
            'payer_id' => User::factory(),
            'payee_id' => User::factory(),
            'amount' => $amount,
            'platform_fee' => $platformFee,
            'status' => fake()->randomElement([
                Payment::STATUS_PENDING,
                Payment::STATUS_HELD,
                Payment::STATUS_HELD, // Weight towards held status
                Payment::STATUS_RELEASED,
                Payment::STATUS_REFUNDED,
                Payment::STATUS_FAILED,
            ]),
            'payment_method' => fake()->randomElement([
                'stripe_card',
                'stripe_bank',
                'paypal',
                'bank_transfer',
            ]),
            'transaction_id' => fake()->uuid(),
        ];
    }

    /**
     * Create a pending payment.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_PENDING,
            'transaction_id' => null,
        ]);
    }

    /**
     * Create a held payment (in escrow).
     */
    public function held(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_HELD,
            'transaction_id' => fake()->uuid(),
        ]);
    }

    /**
     * Create a released payment.
     */
    public function released(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_RELEASED,
            'transaction_id' => fake()->uuid(),
        ]);
    }

    /**
     * Create a refunded payment.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_REFUNDED,
            'transaction_id' => fake()->uuid(),
        ]);
    }

    /**
     * Create a failed payment.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_FAILED,
            'transaction_id' => null,
        ]);
    }

    /**
     * Create a disputed payment.
     */
    public function disputed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_DISPUTED,
            'transaction_id' => fake()->uuid(),
            'dispute_reason' => fake()->randomElement([
                'Work not completed',
                'Poor quality work',
                'Missed deadline',
                'Not as described',
                'Communication issues',
            ]),
            'dispute_description' => fake()->paragraph(),
            'dispute_priority' => fake()->randomElement(['low', 'medium', 'high']),
            'dispute_created_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Create a payment with Stripe card.
     */
    public function stripeCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'stripe_card',
            'transaction_id' => 'pi_' . fake()->regexify('[a-zA-Z0-9]{24}'),
        ]);
    }

    /**
     * Create a payment with Stripe bank transfer.
     */
    public function stripeBank(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'stripe_bank',
            'transaction_id' => 'pi_' . fake()->regexify('[a-zA-Z0-9]{24}'),
        ]);
    }

    /**
     * Create a payment with PayPal.
     */
    public function paypal(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'paypal',
            'transaction_id' => fake()->regexify('[A-Z0-9]{17}'),
        ]);
    }

    /**
     * Create a payment with bank transfer.
     */
    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'bank_transfer',
            'transaction_id' => 'bt_' . fake()->regexify('[a-zA-Z0-9]{16}'),
        ]);
    }

    /**
     * Create a small payment.
     */
    public function small(): static
    {
        $amount = fake()->randomFloat(2, 25, 100);

        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'platform_fee' => Payment::calculatePlatformFee($amount),
        ]);
    }

    /**
     * Create a large payment.
     */
    public function large(): static
    {
        $amount = fake()->randomFloat(2, 1000, 5000);

        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'platform_fee' => Payment::calculatePlatformFee($amount),
        ]);
    }

    /**
     * Create a payment with custom amount.
     */
    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'platform_fee' => Payment::calculatePlatformFee($amount),
        ]);
    }

    /**
     * Create a payment with custom platform fee percentage.
     */
    public function withFeePercentage(float $amount, float $feePercentage): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'platform_fee' => Payment::calculatePlatformFee($amount, $feePercentage),
        ]);
    }

    /**
     * Create a payment for a specific job.
     */
    public function forJob(int $jobId): static
    {
        return $this->state(fn (array $attributes) => [
            'job_id' => $jobId,
        ]);
    }

    /**
     * Create a payment between specific users.
     */
    public function between(int $payerId, int $payeeId): static
    {
        return $this->state(fn (array $attributes) => [
            'payer_id' => $payerId,
            'payee_id' => $payeeId,
        ]);
    }

    /**
     * Create a payment from a specific payer.
     */
    public function from(int $payerId): static
    {
        return $this->state(fn (array $attributes) => [
            'payer_id' => $payerId,
        ]);
    }

    /**
     * Create a payment to a specific payee.
     */
    public function to(int $payeeId): static
    {
        return $this->state(fn (array $attributes) => [
            'payee_id' => $payeeId,
        ]);
    }

    /**
     * Create a recent payment.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Create an old payment.
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('-6 months', '-1 month'),
        ]);
    }

    /**
     * Create a successful payment flow (pending -> held -> released).
     */
    public function successfulFlow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_RELEASED,
            'transaction_id' => fake()->uuid(),
            'created_at' => fake()->dateTimeBetween('-1 month', '-1 week'),
        ]);
    }

    /**
     * Create a failed payment flow.
     */
    public function failedFlow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_FAILED,
            'transaction_id' => null,
            'created_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }
}
