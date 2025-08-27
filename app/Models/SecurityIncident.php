<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityIncident extends Model
{
    use HasFactory;

    /**
     * Incident types.
     */
    public const TYPE_BRUTE_FORCE = 'brute_force';
    public const TYPE_SQL_INJECTION = 'sql_injection';
    public const TYPE_XSS = 'xss';
    public const TYPE_CSRF = 'csrf';
    public const TYPE_DDoS = 'ddos';
    public const TYPE_MALWARE = 'malware';
    public const TYPE_PHISHING = 'phishing';
    public const TYPE_DATA_BREACH = 'data_breach';
    public const TYPE_UNAUTHORIZED_ACCESS = 'unauthorized_access';
    public const TYPE_SUSPICIOUS_ACTIVITY = 'suspicious_activity';

    /**
     * Severity levels.
     */
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    /**
     * Status values.
     */
    public const STATUS_OPEN = 'open';
    public const STATUS_INVESTIGATING = 'investigating';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_FALSE_POSITIVE = 'false_positive';

    /**
     * The table associated with the model.
     */
    protected $table = 'security_incidents';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'incident_type',
        'severity',
        'status',
        'title',
        'description',
        'source_ip',
        'target_endpoint',
        'http_method',
        'request_headers',
        'request_payload',
        'user_agent',
        'affected_user_id',
        'affected_resources',
        'attack_count',
        'first_detected_at',
        'last_detected_at',
        'is_automated',
        'detection_method',
        'mitigation_actions',
        'assigned_to',
        'investigation_notes',
        'resolved_at',
        'resolution_summary',
        'metadata',
        'notification_sent',
        'reference_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'request_headers' => 'array',
        'request_payload' => 'array',
        'affected_resources' => 'array',
        'mitigation_actions' => 'array',
        'metadata' => 'array',
        'is_automated' => 'boolean',
        'notification_sent' => 'boolean',
        'first_detected_at' => 'datetime',
        'last_detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the affected user.
     */
    public function affectedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affected_user_id');
    }

    /**
     * Get the admin assigned to investigate.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Scope to filter by incident type.
     * @param mixed $query
     */
    public function scopeType($query, string $type)
    {
        return $query->where('incident_type', $type);
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
     * Scope to filter by status.
     * @param mixed $query
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get open incidents.
     * @param mixed $query
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope to get high priority incidents.
     * @param mixed $query
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('severity', [self::SEVERITY_HIGH, self::SEVERITY_CRITICAL]);
    }

    /**
     * Scope to filter by source IP.
     * @param mixed $query
     */
    public function scopeFromIp($query, string $ip)
    {
        return $query->where('source_ip', $ip);
    }

    /**
     * Scope to get recent incidents.
     * @param mixed $query
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('first_detected_at', '>=', now()->subHours($hours));
    }

    /**
     * Check if incident is resolved.
     */
    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    /**
     * Check if incident is critical.
     */
    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }

    /**
     * Check if incident requires immediate attention.
     */
    public function requiresImmediateAttention(): bool
    {
        return $this->isCritical() ||
               ($this->severity === self::SEVERITY_HIGH && $this->status === self::STATUS_OPEN);
    }

    /**
     * Assign incident to an admin.
     */
    public function assignTo(int $adminId): void
    {
        $this->update([
            'assigned_to' => $adminId,
            'status' => self::STATUS_INVESTIGATING,
        ]);
    }

    /**
     * Mark incident as resolved.
     */
    public function markAsResolved(string $summary = null): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolution_summary' => $summary,
        ]);
    }

    /**
     * Mark as false positive.
     */
    public function markAsFalsePositive(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_FALSE_POSITIVE,
            'resolved_at' => now(),
            'resolution_summary' => $reason,
        ]);
    }

    /**
     * Increment attack count.
     */
    public function incrementAttackCount(): void
    {
        $this->increment('attack_count');
        $this->update(['last_detected_at' => now()]);
    }

    /**
     * Add mitigation action.
     */
    public function addMitigationAction(string $action): void
    {
        $actions = $this->mitigation_actions ?? [];
        $actions[] = [
            'action' => $action,
            'timestamp' => now()->toISOString(),
        ];

        $this->update(['mitigation_actions' => $actions]);
    }

    /**
     * Get incident duration in minutes.
     */
    public function getDurationInMinutes(): ?int
    {
        if (! $this->resolved_at) {
            return null;
        }

        return $this->first_detected_at->diffInMinutes($this->resolved_at);
    }

    /**
     * Generate reference ID.
     */
    public function generateReferenceId(): string
    {
        return 'INC-' . now()->format('Ymd') . '-' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($incident) {
            $incident->update([
                'reference_id' => $incident->generateReferenceId(),
            ]);
        });
    }
}
