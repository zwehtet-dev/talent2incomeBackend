<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'audit_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'event_type',
        'auditable_type',
        'auditable_id',
        'user_id',
        'user_type',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
        'http_method',
        'request_data',
        'session_id',
        'transaction_id',
        'description',
        'metadata',
        'severity',
        'is_sensitive',
        'hash',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'request_data' => 'array',
        'metadata' => 'array',
        'is_sensitive' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Get the auditable model.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by event type.
     * @param mixed $query
     */
    public function scopeEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter by severity.
     * @param mixed $query
     */
    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope to filter by user.
     * @param mixed $query
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter sensitive logs.
     * @param mixed $query
     */
    public function scopeSensitive($query, bool $sensitive = true)
    {
        return $query->where('is_sensitive', $sensitive);
    }

    /**
     * Scope to filter by date range.
     * @param mixed $query
     * @param mixed $startDate
     * @param mixed $endDate
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Generate integrity hash for the log entry.
     */
    public function generateHash(): string
    {
        $data = [
            'event_type' => $this->event_type,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'user_id' => $this->user_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'created_at' => $this->created_at?->toISOString(),
        ];

        return hash('sha256', json_encode($data, 64)); // JSON_SORT_KEYS
    }

    /**
     * Verify the integrity of the log entry.
     */
    public function verifyIntegrity(): bool
    {
        return $this->hash === $this->generateHash();
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($auditLog) {
            $auditLog->hash = $auditLog->generateHash();
        });
    }
}
