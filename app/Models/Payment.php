<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class Payment extends Model
{
    use HasFactory;
    use \App\Traits\CacheInvalidation;

    /**
     * Payment status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_HELD = 'held';
    public const STATUS_RELEASED = 'released';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DISPUTED = 'disputed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'job_id',
        'payer_id',
        'payee_id',
        'amount',
        'platform_fee',
        'status',
        'payment_method',
        'transaction_id',
        'dispute_reason',
        'dispute_description',
        'dispute_evidence',
        'dispute_priority',
        'dispute_created_at',
        'dispute_resolved_at',
        'dispute_resolution',
        'dispute_resolution_notes',
        'dispute_resolved_by',
        'refund_amount',
        'released_amount',
        'refunded_at',
        'released_at',
    ];

    /**
     * Get all valid statuses.
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_HELD,
            self::STATUS_RELEASED,
            self::STATUS_REFUNDED,
            self::STATUS_FAILED,
            self::STATUS_DISPUTED,
        ];
    }

    /**
     * The job this payment is for.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * The user making the payment.
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    /**
     * The user receiving the payment.
     */
    public function payee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payee_id');
    }

    /**
     * Get the net amount (amount minus platform fee).
     */
    public function getNetAmountAttribute(): float
    {
        return $this->amount - $this->platform_fee;
    }

    /**
     * Get the platform fee percentage.
     */
    public function getPlatformFeePercentageAttribute(): float
    {
        if ($this->amount == 0) {
            return 0;
        }

        return ($this->platform_fee / $this->amount) * 100;
    }

    /**
     * Check if status transition is valid.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        if (! in_array($newStatus, self::getValidStatuses())) {
            return false;
        }

        $validTransitions = self::getValidTransitions();
        $currentStatus = $this->status;

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }

    /**
     * Transition to a new status.
     */
    public function transitionTo(string $newStatus): bool
    {
        if (! $this->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                "Cannot transition from {$this->status} to {$newStatus}"
            );
        }

        $oldStatus = $this->status;
        $this->status = $newStatus;

        $result = $this->save();

        if ($result) {
            $this->handleStatusTransition($oldStatus, $newStatus);
        }

        return $result;
    }

    /**
     * Hold the payment in escrow.
     */
    public function hold(): bool
    {
        return $this->transitionTo(self::STATUS_HELD);
    }

    /**
     * Release the payment to the payee.
     */
    public function release(): bool
    {
        return $this->transitionTo(self::STATUS_RELEASED);
    }

    /**
     * Refund the payment to the payer.
     */
    public function refund(): bool
    {
        return $this->transitionTo(self::STATUS_REFUNDED);
    }

    /**
     * Mark payment as disputed.
     */
    public function dispute(): bool
    {
        return $this->transitionTo(self::STATUS_DISPUTED);
    }

    /**
     * Mark payment as failed.
     */
    public function fail(): bool
    {
        return $this->transitionTo(self::STATUS_FAILED);
    }

    /**
     * Check if payment is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_RELEASED,
            self::STATUS_REFUNDED,
            self::STATUS_FAILED,
        ]);
    }

    /**
     * Check if payment can be released.
     */
    public function canBeReleased(): bool
    {
        return $this->canTransitionTo(self::STATUS_RELEASED);
    }

    /**
     * Check if payment can be refunded.
     */
    public function canBeRefunded(): bool
    {
        return $this->canTransitionTo(self::STATUS_REFUNDED);
    }

    /**
     * Check if payment can be disputed.
     */
    public function canBeDisputed(): bool
    {
        return $this->canTransitionTo(self::STATUS_DISPUTED);
    }

    /**
     * Calculate platform fee based on amount.
     */
    public static function calculatePlatformFee(float $amount, float $feePercentage = 5.0): float
    {
        return round($amount * ($feePercentage / 100), 2);
    }

    /**
     * Scope to filter by status.
     * @param mixed $query
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter pending payments.
     * @param mixed $query
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to filter held payments.
     * @param mixed $query
     */
    public function scopeHeld($query)
    {
        return $query->where('status', self::STATUS_HELD);
    }

    /**
     * Scope to filter released payments.
     * @param mixed $query
     */
    public function scopeReleased($query)
    {
        return $query->where('status', self::STATUS_RELEASED);
    }

    /**
     * Scope to filter disputed payments.
     * @param mixed $query
     */
    public function scopeDisputed($query)
    {
        return $query->where('status', self::STATUS_DISPUTED);
    }

    /**
     * Scope to filter payments for a specific user (as payer or payee).
     * @param mixed $query
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('payer_id', $userId)
            ->orWhere('payee_id', $userId);
    }

    /**
     * Scope to filter payments made by a user.
     * @param mixed $query
     */
    public function scopeMadeBy($query, int $userId)
    {
        return $query->where('payer_id', $userId);
    }

    /**
     * Scope to filter payments received by a user.
     * @param mixed $query
     */
    public function scopeReceivedBy($query, int $userId)
    {
        return $query->where('payee_id', $userId);
    }

    /**
     * Scope to include relationships.
     * @param mixed $query
     */
    public function scopeWithRelations($query)
    {
        return $query->with([
            'job:id,title,status',
            'payer:id,first_name,last_name,email',
            'payee:id,first_name,last_name,email',
        ]);
    }

    /**
     * Scope to filter by amount range.
     * @param mixed $query
     */
    public function scopeInAmountRange($query, ?float $minAmount = null, ?float $maxAmount = null)
    {
        if ($minAmount !== null) {
            $query->where('amount', '>=', $minAmount);
        }

        if ($maxAmount !== null) {
            $query->where('amount', '<=', $maxAmount);
        }

        return $query;
    }

    /**
     * Scope to order by amount.
     * @param mixed $query
     */
    public function scopeOrderByAmount($query, string $direction = 'desc')
    {
        return $query->orderBy('amount', $direction);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'released_amount' => 'decimal:2',
            'dispute_evidence' => 'array',
            'dispute_created_at' => 'datetime',
            'dispute_resolved_at' => 'datetime',
            'refunded_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * Define valid status transitions.
     */
    private static function getValidTransitions(): array
    {
        return [
            self::STATUS_PENDING => [self::STATUS_HELD, self::STATUS_FAILED],
            self::STATUS_HELD => [self::STATUS_RELEASED, self::STATUS_REFUNDED, self::STATUS_DISPUTED],
            self::STATUS_DISPUTED => [self::STATUS_RELEASED, self::STATUS_REFUNDED],
            self::STATUS_RELEASED => [], // Terminal state
            self::STATUS_REFUNDED => [], // Terminal state
            self::STATUS_FAILED => [], // Terminal state
        ];
    }

    /**
     * Handle side effects of status transitions.
     */
    private function handleStatusTransition(string $oldStatus, string $newStatus): void
    {
        switch ($newStatus) {
            case self::STATUS_HELD:
                // Payment has been successfully processed and held in escrow
                $this->job->update(['status' => Job::STATUS_IN_PROGRESS]);

                break;

            case self::STATUS_RELEASED:
                // Payment has been released to the payee
                $this->job->update(['status' => Job::STATUS_COMPLETED]);

                break;

            case self::STATUS_REFUNDED:
                // Payment has been refunded to the payer
                if ($this->job->status === Job::STATUS_IN_PROGRESS) {
                    $this->job->update(['status' => Job::STATUS_CANCELLED]);
                }

                break;

            case self::STATUS_FAILED:
                // Payment processing failed
                $this->job->update(['status' => Job::STATUS_OPEN]);

                break;
        }
    }
}
