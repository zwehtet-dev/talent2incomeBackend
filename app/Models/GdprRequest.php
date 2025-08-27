<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GdprRequest extends Model
{
    use HasFactory;

    /**
     * Request types.
     */
    public const TYPE_EXPORT = 'export';
    public const TYPE_DELETE = 'delete';
    public const TYPE_RECTIFY = 'rectify';
    public const TYPE_RESTRICT = 'restrict';
    public const TYPE_OBJECT = 'object';

    /**
     * Request statuses.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The table associated with the model.
     */
    protected $table = 'gdpr_requests';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'request_type',
        'status',
        'description',
        'requested_data',
        'verification_token',
        'verified_at',
        'processed_at',
        'completed_at',
        'export_file_path',
        'export_file_hash',
        'export_expires_at',
        'admin_notes',
        'processed_by',
        'rejection_reason',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'requested_data' => 'array',
        'metadata' => 'array',
        'verified_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'export_expires_at' => 'datetime',
    ];

    /**
     * Get the user who made the request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who processed the request.
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope to filter by request type.
     * @param mixed $query
     */
    public function scopeType($query, string $type)
    {
        return $query->where('request_type', $type);
    }

    /**
     * Scope to filter by status.
     * @param mixed $query
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get pending requests.
     * @param mixed $query
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get verified requests.
     * @param mixed $query
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Scope to get expired export files.
     * @param mixed $query
     */
    public function scopeExpiredExports($query)
    {
        return $query->where('export_expires_at', '<', now())
            ->whereNotNull('export_file_path');
    }

    /**
     * Check if the request is verified.
     */
    public function isVerified(): bool
    {
        return ! is_null($this->verified_at);
    }

    /**
     * Check if the request is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the export file has expired.
     */
    public function isExportExpired(): bool
    {
        return $this->export_expires_at && $this->export_expires_at->isPast();
    }

    /**
     * Generate verification token.
     */
    public function generateVerificationToken(): string
    {
        return Str::random(64);
    }

    /**
     * Mark request as verified.
     */
    public function markAsVerified(): void
    {
        $this->update([
            'verified_at' => now(),
            'verification_token' => null,
        ]);
    }

    /**
     * Mark request as processing.
     */
    public function markAsProcessing(int $adminId): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'processed_at' => now(),
            'processed_by' => $adminId,
        ]);
    }

    /**
     * Mark request as completed.
     */
    public function markAsCompleted(array $data = []): void
    {
        $this->update(array_merge([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ], $data));
    }

    /**
     * Mark request as rejected.
     */
    public function markAsRejected(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'completed_at' => now(),
        ]);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($request) {
            if (! $request->verification_token) {
                $request->verification_token = $request->generateVerificationToken();
            }
        });
    }
}
