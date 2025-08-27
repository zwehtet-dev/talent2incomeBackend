<?php

namespace Tests\Integration;

use App\Models\Job;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentGatewayIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set test environment variables
        config([
            'services.stripe.key' => 'pk_test_fake_key',
            'services.stripe.secret' => 'sk_test_fake_secret',
            'services.paypal.mode' => 'sandbox',
            'services.paypal.client_id' => 'test_client_id',
            'services.paypal.client_secret' => 'test_client_secret',
        ]);
    }

    public function test_stripe_payment_intent_creation()
    {
        Http::fake([
            'api.stripe.com/v1/payment_intents' => Http::response([
                'id' => 'pi_test_123456789',
                'amount' => 50000, // $500.00 in cents
                'currency' => 'usd',
                'status' => 'requires_payment_method',
                'client_secret' => 'pi_test_123456789_secret_test',
            ], 200),
        ]);

        $payer = User::factory()->create();
        $payee = User::factory()->create();
        $job = Job::factory()->create();

        $paymentService = app(PaymentService::class);

        $result = $paymentService->createStripePaymentIntent([
            'amount' => 500.00,
            'currency' => 'usd',
            'job_id' => $job->id,
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('pi_test_123456789', $result['payment_intent_id']);
        $this->assertNotNull($result['client_secret']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.stripe.com/v1/payment_intents' &&
                   $request['amount'] === 50000 &&
                   $request['currency'] === 'usd';
        });
    }

    public function test_stripe_payment_confirmation()
    {
        Http::fake([
            'api.stripe.com/v1/payment_intents/pi_test_123456789' => Http::response([
                'id' => 'pi_test_123456789',
                'amount' => 50000,
                'currency' => 'usd',
                'status' => 'succeeded',
                'charges' => [
                    'data' => [
                        [
                            'id' => 'ch_test_123456789',
                            'amount' => 50000,
                            'paid' => true,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $payment = Payment::factory()->create([
            'transaction_id' => 'pi_test_123456789',
            'status' => 'pending',
            'payment_method' => 'stripe',
        ]);

        $paymentService = app(PaymentService::class);

        $result = $paymentService->confirmStripePayment('pi_test_123456789');

        $this->assertTrue($result['success']);
        $this->assertSame('succeeded', $result['status']);

        $payment->refresh();
        $this->assertSame('held', $payment->status);
    }

    public function test_stripe_payment_failure_handling()
    {
        Http::fake([
            'api.stripe.com/v1/payment_intents' => Http::response([
                'error' => [
                    'type' => 'card_error',
                    'code' => 'card_declined',
                    'message' => 'Your card was declined.',
                ],
            ], 402),
        ]);

        $payer = User::factory()->create();
        $payee = User::factory()->create();
        $job = Job::factory()->create();

        $paymentService = app(PaymentService::class);

        $result = $paymentService->createStripePaymentIntent([
            'amount' => 500.00,
            'currency' => 'usd',
            'job_id' => $job->id,
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('card_declined', $result['error_code']);
        $this->assertStringContainsString('declined', $result['error_message']);
    }

    public function test_paypal_order_creation()
    {
        Http::fake([
            'api.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'test_access_token',
                'token_type' => 'Bearer',
                'expires_in' => 32400,
            ], 200),
            'api.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'ORDER_123456789',
                'status' => 'CREATED',
                'links' => [
                    [
                        'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER_123456789',
                        'rel' => 'approve',
                        'method' => 'GET',
                    ],
                ],
            ], 201),
        ]);

        $payer = User::factory()->create();
        $payee = User::factory()->create();
        $job = Job::factory()->create();

        $paymentService = app(PaymentService::class);

        $result = $paymentService->createPayPalOrder([
            'amount' => 500.00,
            'currency' => 'USD',
            'job_id' => $job->id,
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('ORDER_123456789', $result['order_id']);
        $this->assertNotNull($result['approval_url']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'v2/checkout/orders') &&
                   $request['purchase_units'][0]['amount']['value'] === '500.00';
        });
    }

    public function test_paypal_order_capture()
    {
        Http::fake([
            'api.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'test_access_token',
                'token_type' => 'Bearer',
                'expires_in' => 32400,
            ], 200),
            'api.sandbox.paypal.com/v2/checkout/orders/ORDER_123456789/capture' => Http::response([
                'id' => 'ORDER_123456789',
                'status' => 'COMPLETED',
                'purchase_units' => [
                    [
                        'payments' => [
                            'captures' => [
                                [
                                    'id' => 'CAPTURE_123456789',
                                    'status' => 'COMPLETED',
                                    'amount' => [
                                        'currency_code' => 'USD',
                                        'value' => '500.00',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $payment = Payment::factory()->create([
            'transaction_id' => 'ORDER_123456789',
            'status' => 'pending',
            'payment_method' => 'paypal',
        ]);

        $paymentService = app(PaymentService::class);

        $result = $paymentService->capturePayPalOrder('ORDER_123456789');

        $this->assertTrue($result['success']);
        $this->assertSame('COMPLETED', $result['status']);

        $payment->refresh();
        $this->assertSame('held', $payment->status);
    }

    public function test_stripe_webhook_handling()
    {
        $payment = Payment::factory()->create([
            'transaction_id' => 'pi_test_123456789',
            'status' => 'pending',
            'payment_method' => 'stripe',
        ]);

        $webhookPayload = [
            'id' => 'evt_test_webhook',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123456789',
                    'status' => 'succeeded',
                    'amount' => 50000,
                    'currency' => 'usd',
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/stripe', $webhookPayload, [
            'Stripe-Signature' => 'test_signature',
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame('held', $payment->status);
    }

    public function test_paypal_webhook_handling()
    {
        $payment = Payment::factory()->create([
            'transaction_id' => 'ORDER_123456789',
            'status' => 'pending',
            'payment_method' => 'paypal',
        ]);

        $webhookPayload = [
            'id' => 'WH-test-webhook',
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => [
                'id' => 'ORDER_123456789',
                'status' => 'APPROVED',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => '500.00',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/paypal', $webhookPayload, [
            'PayPal-Transmission-Id' => 'test_transmission_id',
            'PayPal-Cert-Id' => 'test_cert_id',
            'PayPal-Transmission-Sig' => 'test_signature',
            'PayPal-Transmission-Time' => now()->timestamp,
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame('held', $payment->status);
    }

    public function test_payment_refund_processing()
    {
        Http::fake([
            'api.stripe.com/v1/refunds' => Http::response([
                'id' => 're_test_123456789',
                'amount' => 50000,
                'currency' => 'usd',
                'status' => 'succeeded',
                'charge' => 'ch_test_123456789',
            ], 200),
        ]);

        $payment = Payment::factory()->create([
            'transaction_id' => 'ch_test_123456789',
            'status' => 'released',
            'payment_method' => 'stripe',
            'amount' => 500.00,
        ]);

        $paymentService = app(PaymentService::class);

        $result = $paymentService->refundStripePayment($payment->transaction_id, 500.00);

        $this->assertTrue($result['success']);
        $this->assertSame('re_test_123456789', $result['refund_id']);

        $payment->refresh();
        $this->assertSame('refunded', $payment->status);
    }

    public function test_payment_gateway_timeout_handling()
    {
        Http::fake([
            'api.stripe.com/v1/payment_intents' => Http::response([], 408), // Request Timeout
        ]);

        $payer = User::factory()->create();
        $payee = User::factory()->create();
        $job = Job::factory()->create();

        $paymentService = app(PaymentService::class);

        $result = $paymentService->createStripePaymentIntent([
            'amount' => 500.00,
            'currency' => 'usd',
            'job_id' => $job->id,
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('timeout', $result['error_type']);
    }

    public function test_payment_gateway_rate_limiting()
    {
        Http::fake([
            'api.stripe.com/v1/payment_intents' => Http::response([
                'error' => [
                    'type' => 'rate_limit_error',
                    'message' => 'Too many requests',
                ],
            ], 429),
        ]);

        $payer = User::factory()->create();
        $payee = User::factory()->create();
        $job = Job::factory()->create();

        $paymentService = app(PaymentService::class);

        $result = $paymentService->createStripePaymentIntent([
            'amount' => 500.00,
            'currency' => 'usd',
            'job_id' => $job->id,
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('rate_limit_error', $result['error_type']);
    }

    public function test_payment_idempotency()
    {
        Http::fake([
            'api.stripe.com/v1/payment_intents' => Http::response([
                'id' => 'pi_test_123456789',
                'amount' => 50000,
                'currency' => 'usd',
                'status' => 'requires_payment_method',
                'client_secret' => 'pi_test_123456789_secret_test',
            ], 200),
        ]);

        $payer = User::factory()->create();
        $payee = User::factory()->create();
        $job = Job::factory()->create();

        $paymentService = app(PaymentService::class);

        $requestData = [
            'amount' => 500.00,
            'currency' => 'usd',
            'job_id' => $job->id,
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'idempotency_key' => 'unique_key_123',
        ];

        // Make the same request twice
        $result1 = $paymentService->createStripePaymentIntent($requestData);
        $result2 = $paymentService->createStripePaymentIntent($requestData);

        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);
        $this->assertSame($result1['payment_intent_id'], $result2['payment_intent_id']);

        // Should only make one actual API call due to idempotency
        Http::assertSentCount(1);
    }
}
