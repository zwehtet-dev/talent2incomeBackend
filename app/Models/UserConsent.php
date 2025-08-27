<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConsent extends Model
{
    use HasFactory;

    /**
     * Consent types.
     */
    public const TYPE_PRIVACY_POLICY = 'privacy_policy';
    public const TYPE_TERMS_OF_SERVICE = 'terms_of_service';
    public const TYPE_MARKETING = 'marketing';
    public const TYPE_COOKIES = 'cookies';
    public const TYPE_DATA_PROCESSING = 'data_processing';
    public const TYPE_THIRD_PARTY_SHARING = 'third_party_sharing';

    /**
     * Consent methods.
     */
    public const METHOD_CHECKBOX = 'checkbox';
    public const METHOD_SIGNATURE = 'signature';
    public const METHOD_VERBAL = 'verbal';
    public const METHOD_IMPLIED = 'implied';
    public const METHOD_OPT_OUT = 'opt_out';

    /**
     * The table associated with the model.
     */
    protected $table = 'user_consents';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'consent_type',
        'consent_version',
        'is_granted',
        'granted_at',
        'withdrawn_at',
        'ip_address',
        'user_agent',
        'consent_text',
        'consent_method',
        'metadata',
        'is_required',
        'expires_at',
        'withdrawal_reason',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_granted' => 'boolean',
        'is_required' => 'boolean',
        'granted_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user who gave consent.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by consent type.
     * @param mixed $query
     */
    public function scopeType($query, string $type)
    {
        return $query->where('consent_type', $type);
    }

    /**
     * Scope to filter by granted status.
     * @param mixed $query
     */
    public function scopeGranted($query, bool $granted = true)
    {
        return $query->where('is_granted', $granted);
    }

    /**
     * Scope to filter by required consents.
     * @param mixed $query
     */
    public function scopeRequired($query, bool $required = true)
    {
        return $query->where('is_required', $required);
    }

    /**
     * Scope to get active consents (granted and not withdrawn).
     * @param mixed $query
     */
    public function scopeActive($query)
    {
        return $query->where('is_granted', true)
            ->whereNull('withdrawn_at');
    }

    /**
     * Scope to get expired consents.
     * @param mixed $query
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now())
            ->whereNotNull('expires_at');
    }

    /**
     * Scope to get consents for a specific user.
     * @param mixed $query
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if consent is currently active.
     */
    public function isActive(): bool
    {
        return $this->is_granted &&
               is_null($this->withdrawn_at) &&
               ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Check if consent has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Grant consent.
     */
    public function grant(array $data = []): void
    {
        $this->update(array_merge([
            'is_granted' => true,
            'granted_at' => now(),
            'withdrawn_at' => null,
            'withdrawal_reason' => null,
        ], $data));
    }

    /**
     * Withdraw consent.
     */
    public function withdraw(string $reason = null): void
    {
        $this->update([
            'is_granted' => false,
            'withdrawn_at' => now(),
            'withdrawal_reason' => $reason,
        ]);
    }

    /**
     * Get consent history for a user and type.
     */
    public static function getHistory(int $userId, string $consentType): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('user_id', $userId)
            ->where('consent_type', $consentType)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get current consent status for a user and type.
     */
    public static function getCurrentConsent(int $userId, string $consentType): ?self
    {
        return static::where('user_id', $userId)
            ->where('consent_type', $consentType)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Check if user has given required consents.
     */
    public static function hasRequiredConsents(int $userId): bool
    {
        $requiredTypes = static::distinct('consent_type')
            ->where('is_required', true)
            ->pluck('consent_type');

        foreach ($requiredTypes as $type) {
            $consent = static::getCurrentConsent($userId, $type);
            if (! $consent || ! $consent->isActive()) {
                return false;
            }
        }

        return true;
    }
}
