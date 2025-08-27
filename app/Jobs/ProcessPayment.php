<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPayment implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;
    public array $backoff = [30, 60, 120, 300, 600]; // Exponential backoff

    public function __construct(
        public Payment $payment,
        public string $action, // 'create', 'release', 'refund', 'capture'
        public array $metadata = []
    ) {
        $this->onQueue('payments');
    }

    public function handle(): void
    {
        try {
            Log::info('Processing payment', [
                'payment_id' => $this->payment->id,
                'action' => $this->action,
                'current_status' => $this->payment->status,
            ]);

            DB::beginTransaction();

            switch ($this->action) {
                case 'create':
                    $this->createPayment();

                    break;
                case 'release':
                    $this->releasePayment();

                    break;
                case 'refund':
                    $this->refundPayment();

                    break;
                case 'capture':
                    $this->capturePayment();

                    break;
                default:
                    throw new \InvalidArgumentException("Unknown payment action: {$this->action}");
            }

            DB::commit();

            Log::info('Payment processed successfully', [
                'payment_id' => $this->payment->id,
                'action' => $this->action,
                'new_status' => $this->payment->fresh()->status,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Payment processing failed', [
                'payment_id' => $this->payment->id,
                'action' => $this->action,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Update payment status to failed if max attempts reached
            if ($this->attempts() >= $this->tries) {
                $this->payment->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage(),
                ]);

                // Notify relevant parties about payment failure
                $this->notifyPaymentFailure();
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Payment processing job failed permanently', [
            'payment_id' => $this->payment->id,
            'action' => $this->action,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }

    protected function createPayment(): void
    {
        // Simulate payment gateway integration
        $this->simulatePaymentGatewayCall('create_payment_intent', [
            'amount' => $this->payment->amount,
            'currency' => 'USD',
            'payment_method' => $this->payment->payment_method,
            'metadata' => array_merge($this->metadata, [
                'job_id' => $this->payment->job_id,
                'platform' => config('app.name'),
            ]),
        ]);

        $this->payment->update([
            'status' => 'held',
            'transaction_id' => 'txn_' . uniqid(),
            'processed_at' => now(),
        ]);

        // Send confirmation emails
        $this->dispatchNotifications('payment_created');
    }

    protected function releasePayment(): void
    {
        if ($this->payment->status !== 'held') {
            throw new \InvalidArgumentException("Payment must be in 'held' status to release");
        }

        $this->simulatePaymentGatewayCall('release_payment', [
            'transaction_id' => $this->payment->transaction_id,
            'amount' => $this->payment->amount - $this->payment->platform_fee,
        ]);

        $this->payment->update([
            'status' => 'released',
            'released_at' => now(),
        ]);

        $this->dispatchNotifications('payment_released');
    }

    protected function refundPayment(): void
    {
        if (! in_array($this->payment->status, ['held', 'released'])) {
            throw new \InvalidArgumentException('Payment cannot be refunded in current status');
        }

        $refundAmount = $this->metadata['refund_amount'] ?? $this->payment->amount;

        $this->simulatePaymentGatewayCall('refund_payment', [
            'transaction_id' => $this->payment->transaction_id,
            'amount' => $refundAmount,
            'reason' => $this->metadata['reason'] ?? 'Requested by user',
        ]);

        $this->payment->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'refund_amount' => $refundAmount,
            'refund_reason' => $this->metadata['reason'] ?? null,
        ]);

        $this->dispatchNotifications('payment_refunded');
    }

    protected function capturePayment(): void
    {
        if ($this->payment->status !== 'pending') {
            throw new \InvalidArgumentException("Payment must be in 'pending' status to capture");
        }

        $this->simulatePaymentGatewayCall('capture_payment', [
            'transaction_id' => $this->payment->transaction_id,
            'amount' => $this->payment->amount,
        ]);

        $this->payment->update([
            'status' => 'held',
            'captured_at' => now(),
        ]);

        $this->dispatchNotifications('payment_captured');
    }

    protected function simulatePaymentGatewayCall(string $operation, array $params): array
    {
        // Simulate API call delay
        usleep(rand(100000, 500000)); // 0.1-0.5 seconds

        // Simulate occasional failures for testing retry logic
        if (rand(1, 100) <= 5) { // 5% failure rate
            throw new \Exception('Payment gateway temporarily unavailable');
        }

        Log::info('Payment gateway operation', [
            'operation' => $operation,
            'params' => $params,
            'payment_id' => $this->payment->id,
        ]);

        return [
            'success' => true,
            'transaction_id' => $params['transaction_id'] ?? 'txn_' . uniqid(),
            'timestamp' => now()->toISOString(),
        ];
    }

    protected function dispatchNotifications(string $event): void
    {
        // Notify payer
        SendEmailNotification::dispatch(
            $this->payment->payer,
            $this->getEmailTemplate($event, 'payer'),
            [
                'payment' => $this->payment,
                'job' => $this->payment->job,
                'amount' => $this->payment->amount,
            ]
        );

        // Notify payee
        SendEmailNotification::dispatch(
            $this->payment->payee,
            $this->getEmailTemplate($event, 'payee'),
            [
                'payment' => $this->payment,
                'job' => $this->payment->job,
                'amount' => $this->payment->amount - $this->payment->platform_fee,
            ]
        );
    }

    protected function notifyPaymentFailure(): void
    {
        // Notify both parties about payment failure
        foreach ([$this->payment->payer, $this->payment->payee] as $user) {
            SendEmailNotification::dispatch(
                $user,
                'payment_failed',
                [
                    'payment' => $this->payment,
                    'job' => $this->payment->job,
                    'reason' => $this->payment->failure_reason,
                ]
            );
        }

        // Notify admins for manual review
        $admins = User::where('is_admin', true)->get();
        foreach ($admins as $admin) {
            SendEmailNotification::dispatch(
                $admin,
                'payment_failure_admin',
                [
                    'payment' => $this->payment,
                    'job' => $this->payment->job,
                    'attempts' => $this->attempts(),
                ]
            );
        }
    }

    protected function getEmailTemplate(string $event, string $recipient): string
    {
        $templates = [
            'payment_created' => [
                'payer' => 'payment_created_payer',
                'payee' => 'payment_created_payee',
            ],
            'payment_released' => [
                'payer' => 'payment_released_payer',
                'payee' => 'payment_received',
            ],
            'payment_refunded' => [
                'payer' => 'payment_refunded_payer',
                'payee' => 'payment_refunded_payee',
            ],
            'payment_captured' => [
                'payer' => 'payment_captured_payer',
                'payee' => 'payment_captured_payee',
            ],
        ];

        return $templates[$event][$recipient] ?? 'payment_notification';
    }
}
