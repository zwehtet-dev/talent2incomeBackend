<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\CreatePaymentRequest;
use App\Http\Requests\Payment\PaymentHistoryRequest;
use App\Http\Requests\Payment\RefundPaymentRequest;
use App\Http\Requests\Payment\ReleasePaymentRequest;
use App\Models\Job;
use App\Models\Payment;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Create a new payment for a completed job.
     */
    public function create(CreatePaymentRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $job = Job::with(['user', 'assignedUser'])->findOrFail($request->validated('job_id'));
            $amount = $request->validated('amount');
            $platformFee = Payment::calculatePlatformFee($amount);

            // Create payment record
            $payment = Payment::create([
                'job_id' => $job->id,
                'payer_id' => $job->user_id,
                'payee_id' => $job->assigned_to,
                'amount' => $amount,
                'platform_fee' => $platformFee,
                'status' => Payment::STATUS_PENDING,
                'payment_method' => $request->validated('payment_method'),
                'transaction_id' => $this->generateTransactionId(),
            ]);

            // Process payment with external provider (simulated)
            $paymentResult = $this->processPaymentWithProvider($payment, $request->validated());

            if ($paymentResult['success']) {
                // Hold payment in escrow
                $payment->hold();

                Log::info('Payment created and held in escrow', [
                    'payment_id' => $payment->id,
                    'job_id' => $job->id,
                    'amount' => $amount,
                    'payer_id' => $job->user_id,
                    'payee_id' => $job->assigned_to,
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'Payment created successfully and held in escrow.',
                    'data' => [
                        'payment' => $this->formatPaymentResponse($payment->load(['job', 'payer', 'payee'])),
                        'escrow_details' => [
                            'amount_held' => $payment->amount,
                            'platform_fee' => $payment->platform_fee,
                            'net_amount' => $payment->net_amount,
                            'release_instructions' => 'Payment will be held in escrow until you release it to the service provider.',
                        ],
                    ],
                ], 201);
            } else {
                // Mark payment as failed
                $payment->fail();

                DB::commit();

                return response()->json([
                    'message' => 'Payment processing failed.',
                    'error' => $paymentResult['error'] ?? 'Unknown payment error',
                    'data' => [
                        'payment' => $this->formatPaymentResponse($payment->load(['job', 'payer', 'payee'])),
                    ],
                ], 422);
            }
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Payment creation failed', [
                'error' => $e->getMessage(),
                'job_id' => $request->validated('job_id'),
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Payment creation failed.',
                'error' => 'An error occurred while processing your payment. Please try again.',
            ], 500);
        }
    }

    /**
     * Release payment from escrow to the service provider.
     */
    public function release(ReleasePaymentRequest $request, Payment $payment): JsonResponse
    {
        $this->authorize('release', $payment);

        try {
            DB::beginTransaction();

            if (! $payment->canBeReleased()) {
                return response()->json([
                    'message' => 'Payment cannot be released in its current state.',
                    'current_status' => $payment->status,
                ], 422);
            }

            // Release payment
            $payment->release();

            // Process actual fund transfer (simulated)
            $transferResult = $this->transferFundsToPayee($payment);

            if ($transferResult['success']) {
                Log::info('Payment released successfully', [
                    'payment_id' => $payment->id,
                    'job_id' => $payment->job_id,
                    'amount' => $payment->amount,
                    'payee_id' => $payment->payee_id,
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'Payment released successfully.',
                    'data' => [
                        'payment' => $this->formatPaymentResponse($payment->load(['job', 'payer', 'payee'])),
                        'transfer_details' => [
                            'amount_transferred' => $payment->net_amount,
                            'platform_fee_deducted' => $payment->platform_fee,
                            'transfer_id' => $transferResult['transfer_id'] ?? null,
                        ],
                    ],
                ]);
            } else {
                // Revert status if transfer failed
                $payment->update(['status' => Payment::STATUS_HELD]);

                DB::commit();

                return response()->json([
                    'message' => 'Payment release failed.',
                    'error' => $transferResult['error'] ?? 'Fund transfer failed',
                ], 500);
            }
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Payment release failed', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Payment release failed.',
                'error' => 'An error occurred while releasing the payment. Please try again.',
            ], 500);
        }
    }

    /**
     * Process refund request.
     */
    public function refund(RefundPaymentRequest $request, Payment $payment): JsonResponse
    {
        $this->authorize('requestRefund', $payment);

        try {
            DB::beginTransaction();

            if (! $payment->canBeRefunded()) {
                return response()->json([
                    'message' => 'Payment cannot be refunded in its current state.',
                    'current_status' => $payment->status,
                ], 422);
            }

            // Create dispute record for admin review
            $disputeData = [
                'payment_id' => $payment->id,
                'reason' => $request->validated('reason'),
                'description' => $request->validated('description'),
                'evidence' => $request->validated('evidence', []),
                'requested_by' => $request->user()->id,
                'status' => 'pending_review',
            ];

            // Mark payment as disputed
            $payment->dispute();

            // In a real implementation, you would create a disputes table
            // For now, we'll log the dispute information
            Log::info('Refund request created', [
                'payment_id' => $payment->id,
                'dispute_data' => $disputeData,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Refund request submitted successfully.',
                'data' => [
                    'payment' => $this->formatPaymentResponse($payment->load(['job', 'payer', 'payee'])),
                    'dispute_info' => [
                        'status' => 'pending_review',
                        'reason' => $request->validated('reason'),
                        'estimated_review_time' => '2-5 business days',
                        'next_steps' => 'Our team will review your request and contact you with updates.',
                    ],
                ],
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Refund request failed', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Refund request failed.',
                'error' => 'An error occurred while processing your refund request. Please try again.',
            ], 500);
        }
    }

    /**
     * Get payment history for the authenticated user.
     */
    public function history(PaymentHistoryRequest $request): JsonResponse
    {
        // Check if user can view payment history
        if (! $request->user()->is_active || ! $request->user()->email_verified_at) {
            abort(403, 'Access denied. Account must be active and verified.');
        }

        $user = $request->user();
        $validated = $request->validated();

        $query = Payment::query()->withRelations();

        // Filter by user involvement
        switch ($validated['type']) {
            case 'sent':
                $query->madeBy($user->id);

                break;
            case 'received':
                $query->receivedBy($user->id);

                break;
            default:
                $query->forUser($user->id);

                break;
        }

        // Apply filters
        if (! empty($validated['status'])) {
            $query->withStatus($validated['status']);
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        if (! empty($validated['amount_min']) || ! empty($validated['amount_max'])) {
            $query->inAmountRange($validated['amount_min'], $validated['amount_max']);
        }

        if (! empty($validated['job_id'])) {
            $query->where('job_id', $validated['job_id']);
        }

        // Apply sorting
        $query->orderBy($validated['sort_by'], $validated['sort_direction']);

        // Paginate results
        $payments = $query->paginate($validated['per_page'], ['*'], 'page', $validated['page']);

        // Calculate summary statistics
        $summaryQuery = clone $query;
        $summaryQuery->getQuery()->orders = null; // Remove ordering for aggregation

        $summary = [
            'total_sent' => (clone $summaryQuery)->madeBy($user->id)->sum('amount'),
            'total_received' => (clone $summaryQuery)->receivedBy($user->id)->sum('net_amount'),
            'total_platform_fees' => (clone $summaryQuery)->madeBy($user->id)->sum('platform_fee'),
            'pending_amount' => (clone $summaryQuery)->forUser($user->id)->pending()->sum('amount'),
            'held_amount' => (clone $summaryQuery)->forUser($user->id)->held()->sum('amount'),
        ];

        return response()->json([
            'data' => collect($payments->items())->map(function ($payment) {
                return $this->formatPaymentResponse($payment);
            })->toArray(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ],
            'summary' => $summary,
            'filters_applied' => array_filter($validated, function ($value) {
                return ! is_null($value) && $value !== '';
            }),
        ]);
    }

    /**
     * Get specific payment details.
     */
    public function show(Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        $payment->load(['job', 'payer', 'payee']);

        return response()->json([
            'data' => $this->formatPaymentResponse($payment, true),
        ]);
    }

    /**
     * Get payment statistics for the authenticated user.
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();

        $stats = [
            'payments_sent' => [
                'total_count' => Payment::madeBy($user->id)->count(),
                'total_amount' => Payment::madeBy($user->id)->sum('amount'),
                'pending_count' => Payment::madeBy($user->id)->pending()->count(),
                'held_count' => Payment::madeBy($user->id)->held()->count(),
                'completed_count' => Payment::madeBy($user->id)->released()->count(),
            ],
            'payments_received' => [
                'total_count' => Payment::receivedBy($user->id)->count(),
                'total_amount' => Payment::receivedBy($user->id)->sum('net_amount'),
                'pending_count' => Payment::receivedBy($user->id)->pending()->count(),
                'held_count' => Payment::receivedBy($user->id)->held()->count(),
                'completed_count' => Payment::receivedBy($user->id)->released()->count(),
            ],
            'platform_fees_paid' => Payment::madeBy($user->id)->sum('platform_fee'),
            'disputed_payments' => Payment::forUser($user->id)->disputed()->count(),
            'average_payment_sent' => Payment::madeBy($user->id)->avg('amount'),
            'average_payment_received' => Payment::receivedBy($user->id)->avg('net_amount'),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Process payment with external provider (simulated).
     */
    private function processPaymentWithProvider(Payment $payment, array $paymentData): array
    {
        // This is a simulation of payment processing
        // In a real implementation, you would integrate with Stripe, PayPal, etc.

        try {
            // Simulate payment processing delay
            usleep(500000); // 0.5 seconds

            // Simulate 95% success rate
            $success = rand(1, 100) <= 95;

            if ($success) {
                return [
                    'success' => true,
                    'transaction_id' => 'txn_' . uniqid(),
                    'provider_response' => 'Payment processed successfully',
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Payment declined by provider',
                    'error_code' => 'CARD_DECLINED',
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Payment processing error: ' . $e->getMessage(),
                'error_code' => 'PROCESSING_ERROR',
            ];
        }
    }

    /**
     * Transfer funds to payee (simulated).
     */
    private function transferFundsToPayee(Payment $payment): array
    {
        // This is a simulation of fund transfer
        // In a real implementation, you would transfer funds to the payee's account

        try {
            // Simulate transfer processing delay
            usleep(300000); // 0.3 seconds

            // Simulate 98% success rate for transfers
            $success = rand(1, 100) <= 98;

            if ($success) {
                return [
                    'success' => true,
                    'transfer_id' => 'tr_' . uniqid(),
                    'amount' => $payment->net_amount,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Transfer failed - recipient account issue',
                    'error_code' => 'TRANSFER_FAILED',
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Transfer processing error: ' . $e->getMessage(),
                'error_code' => 'TRANSFER_ERROR',
            ];
        }
    }

    /**
     * Generate a unique transaction ID.
     */
    private function generateTransactionId(): string
    {
        return 'pay_' . time() . '_' . uniqid();
    }

    /**
     * Format payment response data.
     */
    private function formatPaymentResponse(Payment $payment, bool $detailed = false): array
    {
        $data = [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'platform_fee' => $payment->platform_fee,
            'net_amount' => $payment->net_amount,
            'status' => $payment->status,
            'payment_method' => $payment->payment_method,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
            'job' => [
                'id' => $payment->job->id,
                'title' => $payment->job->title,
                'status' => $payment->job->status,
            ],
            'payer' => [
                'id' => $payment->payer->id,
                'name' => $payment->payer->first_name . ' ' . $payment->payer->last_name,
                'email' => $payment->payer->email,
            ],
            'payee' => [
                'id' => $payment->payee->id,
                'name' => $payment->payee->first_name . ' ' . $payment->payee->last_name,
                'email' => $payment->payee->email,
            ],
        ];

        if ($detailed) {
            $data['transaction_id'] = $payment->transaction_id;
            $data['platform_fee_percentage'] = $payment->platform_fee_percentage;
            $data['can_be_released'] = $payment->canBeReleased();
            $data['can_be_refunded'] = $payment->canBeRefunded();
            $data['can_be_disputed'] = $payment->canBeDisputed();
            $data['is_terminal'] = $payment->isTerminal();
        }

        return $data;
    }
}
