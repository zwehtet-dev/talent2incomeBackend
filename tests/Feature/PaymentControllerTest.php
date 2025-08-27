<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Job;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    private User $client;
    private User $serviceProvider;
    private Category $category;
    private Job $completedJob;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->client = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->serviceProvider = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // Create category
        $this->category = Category::factory()->create();

        // Create completed job
        $this->completedJob = Job::factory()->create([
            'user_id' => $this->client->id,
            'category_id' => $this->category->id,
            'status' => Job::STATUS_COMPLETED,
            'assigned_to' => $this->serviceProvider->id,
            'budget_min' => 100.00,
            'budget_max' => 200.00,
        ]);
    }

    public function test_create_payment_successfully(): void
    {
        Sanctum::actingAs($this->client);

        $paymentData = [
            'job_id' => $this->completedJob->id,
            'amount' => 150.00,
            'payment_method' => 'stripe',
            'payment_method_token' => 'tok_visa',
            'description' => 'Payment for completed work',
        ];

        $response = $this->postJson('/api/payments/create', $paymentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'payment' => [
                        'id',
                        'amount',
                        'platform_fee',
                        'net_amount',
                        'status',
                        'payment_method',
                        'job',
                        'payer',
                        'payee',
                    ],
                    'escrow_details' => [
                        'amount_held',
                        'platform_fee',
                        'net_amount',
                        'release_instructions',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'job_id' => $this->completedJob->id,
            'payer_id' => $this->client->id,
            'payee_id' => $this->serviceProvider->id,
            'amount' => 150.00,
            'status' => Payment::STATUS_HELD,
            'payment_method' => 'stripe',
        ]);

        // Check that job status was updated
        $this->completedJob->refresh();
        $this->assertSame(Job::STATUS_IN_PROGRESS, $this->completedJob->status);
    }

    public function test_create_payment_fails_for_non_completed_job(): void
    {
        Sanctum::actingAs($this->client);

        $openJob = Job::factory()->create([
            'user_id' => $this->client->id,
            'category_id' => $this->category->id,
            'status' => Job::STATUS_OPEN,
        ]);

        $paymentData = [
            'job_id' => $openJob->id,
            'amount' => 150.00,
            'payment_method' => 'stripe',
        ];

        $response = $this->postJson('/api/payments/create', $paymentData);

        $response->assertStatus(403);
    }

    public function test_create_payment_fails_for_job_without_assigned_user(): void
    {
        Sanctum::actingAs($this->client);

        $jobWithoutAssignee = Job::factory()->create([
            'user_id' => $this->client->id,
            'category_id' => $this->category->id,
            'status' => Job::STATUS_COMPLETED,
            'assigned_to' => null,
        ]);

        $paymentData = [
            'job_id' => $jobWithoutAssignee->id,
            'amount' => 150.00,
            'payment_method' => 'stripe',
        ];

        $response = $this->postJson('/api/payments/create', $paymentData);

        $response->assertStatus(403);
    }

    public function test_create_payment_validates_amount(): void
    {
        Sanctum::actingAs($this->client);

        $paymentData = [
            'job_id' => $this->completedJob->id,
            'amount' => -50.00, // Invalid negative amount
            'payment_method' => 'stripe',
        ];

        $response = $this->postJson('/api/payments/create', $paymentData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_payment_prevents_duplicate_payments(): void
    {
        Sanctum::actingAs($this->client);

        // Create existing payment
        Payment::factory()->create([
            'job_id' => $this->completedJob->id,
            'payer_id' => $this->client->id,
            'payee_id' => $this->serviceProvider->id,
        ]);

        $paymentData = [
            'job_id' => $this->completedJob->id,
            'amount' => 150.00,
            'payment_method' => 'stripe',
        ];

        $response = $this->postJson('/api/payments/create', $paymentData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['job_id']);
    }

    public function test_release_payment_successfully(): void
    {
        Sanctum::actingAs($this->client);

        $payment = Payment::factory()->create([
            'job_id' => $this->completedJob->id,
            'payer_id' => $this->client->id,
            'payee_id' => $this->serviceProvider->id,
            'status' => Payment::STATUS_HELD,
            'amount' => 150.00,
        ]);

        $releaseData = [
            'confirmation' => true,
            'notes' => 'Work completed satisfactorily',
        ];

        $response = $this->postJson("/api/payments/{$payment->id}/release", $releaseData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'payment',
                    'transfer_details' => [
                        'amount_transferred',
                        'platform_fee_deducted',
                        'transfer_id',
                    ],
                ],
            ]);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_RELEASED, $payment->status);

        // Check that job status was updated
        $this->completedJob->refresh();
        $this->assertSame(Job::STATUS_COMPLETED, $this->completedJob->status);
    }

    public function test_release_payment_fails_for_non_payer(): void
    {
        Sanctum::actingAs($this->serviceProvider); // Wrong user

        $payment = Payment::factory()->create([
            'job_id' => $this->completedJob->id,
            'payer_id' => $this->client->id,
            'payee_id' => $this->serviceProvider->id,
            'status' => Payment::STATUS_HELD,
        ]);

        $releaseData = [
            'confirmation' => true,
        ];

        $response = $this->postJson("/api/payments/{$payment->id}/release", $releaseData);

        $response->assertStatus(403);
    }

    public function test_release_payment_fails_for_non_held_payment(): void
    {
        Sanctum::actingAs($this->client);

        $payment = Payment::factory()->create([
            'job_id' => $this->completedJob->id,
            'payer_id' => $this->client->id,
            'payee_id' => $this->serviceProvider->id,
            'status' => Payment::STATUS_PENDING, // Wrong status
        ]);

        $releaseData = [
            'confirmation' => true,
        ];

        $response = $this->postJson("/api/payments/{$payment->id}/release", $releaseData);

        $response->assertStatus(422);
    }

    public function test_refund_payment_successfully(): void
    {
        Sanctum::actingAs($this->client);

        $payment = Payment::factory()->create([
            'job_id' => $this->completedJob->id,
            'payer_id' => $this->client->id,
            'payee_id' => $this->serviceProvider->id,
            'status' => Payment::STATUS_HELD,
        ]);

        $refundData = [
            'reason' => 'work_not_completed',
            'description' => 'The service provider did not complete the work as agreed.',
            'evidence' => ['screenshot1.png', 'email_thread.pdf'],
        ];

        $response = $this->postJson("/api/payments/{$payment->id}/refund", $refundData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'payment',
                    'dispute_info' => [
                        'status',
                        'reason',
                        'estimated_review_time',
                        'next_steps',
                    ],
                ],
            ]);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_DISPUTED, $payment->status);
    }

    public function test_refund_payment_validates_reason(): void
    {
        Sanctum::actingAs($this->client);

        $payment = Payment::factory()->create([
            'job_id' => $this->completedJob->id,
            'payer_id' => $this->client->id,
            'payee_id' => $this->serviceProvider->id,
            'status' => Payment::STATUS_HELD,
        ]);

        $refundData = [
            'reason' => 'invalid_reason',
            'description' => 'Some description',
        ];

        $response = $this->postJson("/api/payments/{$payment->id}/refund", $refundData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_get_payment_history_successfully(): void
    {
        Sanctum::actingAs($this->client);

        // Create multiple payments
        $sentPayment = Payment::factory()->create([
            'payer_id' => $this->client->id,
            'payee_id' => $this->serviceProvider->id,
            'status' => Payment::STATUS_RELEASED,
            'amount' => 100.00,
        ]);

        $receivedPayment = Payment::factory()->create([
            'payer_id' => $this->serviceProvider->id,
            'payee_id' => $this->client->id,
            'status' => Payment::STATUS_HELD,
            'amount' => 200.00,
        ]);

        $response = $this->getJson('/api/payments/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'amount',
                        'platform_fee',
                        'net_amount',
                        'status',
                        'payment_method',
                        'job',
                        'payer',
                        'payee',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
                'summary' => [
                    'total_sent',
                    'total_received',
                    'total_platform_fees',
                    'pending_amount',
                    'held_amount',
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_get_payment_history_with_filters(): void
    {
        Sanctum::actingAs($this->client);

        // Create payments with different statuses
        Payment::factory()->create([
            'payer_id' => $this->client->id,
            'payee_id' => $this->serviceProvider->id,
            'status' => Payment::STATUS_HELD,
        ]);

        Payment::factory()->create([
            'payer_id' => $this->client->id,
            'payee_id' => $this->serviceProvider->id,
            'status' => Payment::STATUS_RELEASED,
        ]);

        $response = $this->getJson('/api/payments/history?status=held&type=sent');

        $response->assertStatus(200);

        $payments = $response->json('data');
        $this->assertCount(1, $payments);
        $this->assertSame(Payment::STATUS_HELD, $payments[0]['status']);
    }

    public function test_show_payment_details(): void
    {
        Sanctum::actingAs($this->client);

        $payment = Payment::factory()->create([
            'job_id' => $this->completedJob->id,
            'payer_id' => $this->client->id,
            'payee_id' => $this->serviceProvider->id,
        ]);

        $response = $this->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'amount',
                    'platform_fee',
                    'net_amount',
                    'status',
                    'payment_method',
                    'transaction_id',
                    'platform_fee_percentage',
                    'can_be_released',
                    'can_be_refunded',
                    'can_be_disputed',
                    'is_terminal',
                    'job',
                    'payer',
                    'payee',
                ],
            ]);
    }

    public function test_show_payment_fails_for_unauthorized_user(): void
    {
        $unauthorizedUser = User::factory()->create();
        Sanctum::actingAs($unauthorizedUser);

        $payment = Payment::factory()->create([
            'payer_id' => $this->client->id,
            'payee_id' => $this->serviceProvider->id,
        ]);

        $response = $this->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(403);
    }

    public function test_get_payment_statistics(): void
    {
        Sanctum::actingAs($this->client);

        // Create various payments
        Payment::factory()->create([
            'payer_id' => $this->client->id,
            'payee_id' => $this->serviceProvider->id,
            'status' => Payment::STATUS_RELEASED,
            'amount' => 100.00,
            'platform_fee' => 5.00,
        ]);

        Payment::factory()->create([
            'payer_id' => $this->serviceProvider->id,
            'payee_id' => $this->client->id,
            'status' => Payment::STATUS_HELD,
            'amount' => 200.00,
            'platform_fee' => 10.00,
        ]);

        $response = $this->getJson('/api/payments/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'payments_sent' => [
                        'total_count',
                        'total_amount',
                        'pending_count',
                        'held_count',
                        'completed_count',
                    ],
                    'payments_received' => [
                        'total_count',
                        'total_amount',
                        'pending_count',
                        'held_count',
                        'completed_count',
                    ],
                    'platform_fees_paid',
                    'disputed_payments',
                    'average_payment_sent',
                    'average_payment_received',
                ],
            ]);

        $stats = $response->json('data');
        $this->assertSame(1, $stats['payments_sent']['total_count']);
        $this->assertSame(100.00, $stats['payments_sent']['total_amount']);
        $this->assertSame(5.00, $stats['platform_fees_paid']);
    }

    public function test_unauthenticated_requests_fail(): void
    {
        $payment = Payment::factory()->create();

        $endpoints = [
            ['POST', '/api/payments/create'],
            ['GET', '/api/payments/history'],
            ['GET', '/api/payments/statistics'],
            ['GET', "/api/payments/{$payment->id}"],
            ['POST', "/api/payments/{$payment->id}/release"],
            ['POST', "/api/payments/{$payment->id}/refund"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertStatus(401);
        }
    }
}
