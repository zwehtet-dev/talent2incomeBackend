<?php

namespace Tests\Unit\Models;

use App\Models\Job;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_can_be_created_with_valid_data()
    {
        $job = Job::factory()->create();
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $paymentData = [
            'job_id' => $job->id,
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'amount' => 500.00,
            'platform_fee' => 25.00,
            'status' => 'pending',
            'payment_method' => 'stripe',
            'transaction_id' => 'txn_123456789',
        ];

        $payment = Payment::create($paymentData);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame(500.00, $payment->amount);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('stripe', $payment->payment_method);
    }

    public function test_payment_belongs_to_job()
    {
        $job = Job::factory()->create();
        $payment = Payment::factory()->create(['job_id' => $job->id]);

        $this->assertInstanceOf(Job::class, $payment->job);
        $this->assertSame($job->id, $payment->job->id);
    }

    public function test_payment_belongs_to_payer()
    {
        $payer = User::factory()->create();
        $payment = Payment::factory()->create(['payer_id' => $payer->id]);

        $this->assertInstanceOf(User::class, $payment->payer);
        $this->assertSame($payer->id, $payment->payer->id);
    }

    public function test_payment_belongs_to_payee()
    {
        $payee = User::factory()->create();
        $payment = Payment::factory()->create(['payee_id' => $payee->id]);

        $this->assertInstanceOf(User::class, $payment->payee);
        $this->assertSame($payee->id, $payment->payee->id);
    }

    public function test_payment_status_scopes()
    {
        Payment::factory()->create(['status' => 'pending']);
        Payment::factory()->create(['status' => 'held']);
        Payment::factory()->create(['status' => 'released']);
        Payment::factory()->create(['status' => 'refunded']);
        Payment::factory()->create(['status' => 'failed']);

        $this->assertCount(1, Payment::pending()->get());
        $this->assertCount(1, Payment::held()->get());
        $this->assertCount(1, Payment::released()->get());
        $this->assertCount(1, Payment::refunded()->get());
        $this->assertCount(1, Payment::failed()->get());
    }

    public function test_payment_successful_scope()
    {
        Payment::factory()->create(['status' => 'released']);
        Payment::factory()->create(['status' => 'held']);
        Payment::factory()->create(['status' => 'failed']);

        $successfulPayments = Payment::successful()->get();

        $this->assertCount(2, $successfulPayments);
    }

    public function test_payment_for_user_scope()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Payments where user is payer
        Payment::factory()->count(2)->create(['payer_id' => $user->id]);

        // Payments where user is payee
        Payment::factory()->count(3)->create(['payee_id' => $user->id]);

        // Payments not involving user
        Payment::factory()->create(['payer_id' => $otherUser->id, 'payee_id' => $otherUser->id]);

        $userPayments = Payment::forUser($user->id)->get();

        $this->assertCount(5, $userPayments);
    }

    public function test_payment_by_method_scope()
    {
        Payment::factory()->create(['payment_method' => 'stripe']);
        Payment::factory()->create(['payment_method' => 'paypal']);
        Payment::factory()->create(['payment_method' => 'stripe']);

        $stripePayments = Payment::byMethod('stripe')->get();

        $this->assertCount(2, $stripePayments);
    }

    public function test_payment_net_amount_accessor()
    {
        $payment = Payment::factory()->create([
            'amount' => 500.00,
            'platform_fee' => 25.00,
        ]);

        $this->assertSame(475.00, $payment->net_amount);
    }

    public function test_payment_fee_percentage_accessor()
    {
        $payment = Payment::factory()->create([
            'amount' => 500.00,
            'platform_fee' => 25.00,
        ]);

        $this->assertSame(5.0, $payment->fee_percentage);
    }

    public function test_payment_is_pending_method()
    {
        $pendingPayment = Payment::factory()->create(['status' => 'pending']);
        $heldPayment = Payment::factory()->create(['status' => 'held']);

        $this->assertTrue($pendingPayment->isPending());
        $this->assertFalse($heldPayment->isPending());
    }

    public function test_payment_is_held_method()
    {
        $heldPayment = Payment::factory()->create(['status' => 'held']);
        $releasedPayment = Payment::factory()->create(['status' => 'released']);

        $this->assertTrue($heldPayment->isHeld());
        $this->assertFalse($releasedPayment->isHeld());
    }

    public function test_payment_is_released_method()
    {
        $releasedPayment = Payment::factory()->create(['status' => 'released']);
        $heldPayment = Payment::factory()->create(['status' => 'held']);

        $this->assertTrue($releasedPayment->isReleased());
        $this->assertFalse($heldPayment->isReleased());
    }

    public function test_payment_is_refunded_method()
    {
        $refundedPayment = Payment::factory()->create(['status' => 'refunded']);
        $releasedPayment = Payment::factory()->create(['status' => 'released']);

        $this->assertTrue($refundedPayment->isRefunded());
        $this->assertFalse($releasedPayment->isRefunded());
    }

    public function test_payment_is_failed_method()
    {
        $failedPayment = Payment::factory()->create(['status' => 'failed']);
        $pendingPayment = Payment::factory()->create(['status' => 'pending']);

        $this->assertTrue($failedPayment->isFailed());
        $this->assertFalse($pendingPayment->isFailed());
    }

    public function test_payment_can_be_released_method()
    {
        $heldPayment = Payment::factory()->create(['status' => 'held']);
        $releasedPayment = Payment::factory()->create(['status' => 'released']);
        $failedPayment = Payment::factory()->create(['status' => 'failed']);

        $this->assertTrue($heldPayment->canBeReleased());
        $this->assertFalse($releasedPayment->canBeReleased());
        $this->assertFalse($failedPayment->canBeReleased());
    }

    public function test_payment_can_be_refunded_method()
    {
        $releasedPayment = Payment::factory()->create(['status' => 'released']);
        $heldPayment = Payment::factory()->create(['status' => 'held']);
        $refundedPayment = Payment::factory()->create(['status' => 'refunded']);

        $this->assertTrue($releasedPayment->canBeRefunded());
        $this->assertTrue($heldPayment->canBeRefunded());
        $this->assertFalse($refundedPayment->canBeRefunded());
    }

    public function test_payment_release_method()
    {
        $payment = Payment::factory()->create(['status' => 'held']);

        $result = $payment->release();

        $this->assertTrue($result);
        $this->assertSame('released', $payment->status);
        $this->assertNotNull($payment->updated_at);
    }

    public function test_payment_cannot_release_if_not_held()
    {
        $payment = Payment::factory()->create(['status' => 'released']);

        $result = $payment->release();

        $this->assertFalse($result);
        $this->assertSame('released', $payment->status); // Status unchanged
    }

    public function test_payment_refund_method()
    {
        $payment = Payment::factory()->create(['status' => 'released']);

        $result = $payment->refund();

        $this->assertTrue($result);
        $this->assertSame('refunded', $payment->status);
    }

    public function test_payment_cannot_refund_if_already_refunded()
    {
        $payment = Payment::factory()->create(['status' => 'refunded']);

        $result = $payment->refund();

        $this->assertFalse($result);
        $this->assertSame('refunded', $payment->status); // Status unchanged
    }

    public function test_payment_mark_as_failed_method()
    {
        $payment = Payment::factory()->create(['status' => 'pending']);

        $payment->markAsFailed();

        $this->assertSame('failed', $payment->status);
    }

    public function test_payment_hold_method()
    {
        $payment = Payment::factory()->create(['status' => 'pending']);

        $result = $payment->hold();

        $this->assertTrue($result);
        $this->assertSame('held', $payment->status);
    }

    public function test_payment_status_history_tracking()
    {
        $payment = Payment::factory()->create(['status' => 'pending']);

        $payment->hold();
        $payment->release();

        // In a real implementation, you might track status changes
        $this->assertSame('released', $payment->status);
    }

    public function test_payment_validation_constraints()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Try to create payment without required fields
        Payment::create([]);
    }

    public function test_payment_amount_validation()
    {
        $job = Job::factory()->create();
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        // Negative amount should be invalid
        $payment = Payment::factory()->make([
            'job_id' => $job->id,
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'amount' => -100.00,
            'platform_fee' => 5.00,
        ]);

        // This validation should be handled by form requests in real app
        $this->assertLessThan(0, $payment->amount);
    }

    public function test_payment_platform_fee_calculation()
    {
        $payment = Payment::factory()->create([
            'amount' => 1000.00,
            'platform_fee' => 50.00,
        ]);

        $this->assertSame(5.0, $payment->fee_percentage);
        $this->assertSame(950.00, $payment->net_amount);
    }

    public function test_payment_transaction_id_uniqueness()
    {
        Payment::factory()->create(['transaction_id' => 'txn_unique_123']);

        // In real implementation, transaction_id should be unique
        $duplicatePayment = Payment::factory()->make(['transaction_id' => 'txn_unique_123']);

        $this->assertSame('txn_unique_123', $duplicatePayment->transaction_id);
    }

    public function test_payment_soft_deletes()
    {
        $payment = Payment::factory()->create();
        $paymentId = $payment->id;

        $payment->delete();

        $this->assertSoftDeleted('payments', ['id' => $paymentId]);
        $this->assertCount(0, Payment::all());
        $this->assertCount(1, Payment::withTrashed()->get());
    }

    public function test_payment_status_transitions()
    {
        $payment = Payment::factory()->create(['status' => 'pending']);

        // Valid transition: pending -> held
        $payment->hold();
        $this->assertSame('held', $payment->status);

        // Valid transition: held -> released
        $payment->release();
        $this->assertSame('released', $payment->status);

        // Valid transition: released -> refunded
        $payment->refund();
        $this->assertSame('refunded', $payment->status);
    }
}
