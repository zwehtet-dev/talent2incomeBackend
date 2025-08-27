<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;
    use \App\Traits\CacheInvalidation;
    use \App\Traits\HasConsents;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'avatar',
        'bio',
        'location',
        'phone',
        'phone_country_code',
        'phone_verified_at',
        'google_id',
        'provider',
        'provider_data',
        'is_active',
        'is_admin',
        'failed_login_attempts',
        'locked_until',
        'last_login_ip',
        'last_login_at',
        'login_history',
        'cached_average_rating',
        'cached_weighted_rating',
        'cached_quality_score',
        'cached_total_reviews',
        'rating_cache_updated_at',
        'last_activity_at',
        'is_rating_eligible',
        'two_factor_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'phone_verification_code',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Hash the password when setting it.
     * @param mixed $value
     */
    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = Hash::make($value);
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Check if user has verified phone number.
     */
    public function hasVerifiedPhone(): bool
    {
        return ! is_null($this->phone_verified_at);
    }

    /**
     * Check if user is locked out from phone verification.
     */
    public function isPhoneVerificationLocked(): bool
    {
        return $this->phone_verification_locked_until &&
               $this->phone_verification_locked_until->isFuture();
    }

    /**
     * Check if user has Google OAuth linked.
     */
    public function hasGoogleLinked(): bool
    {
        return ! is_null($this->google_id);
    }

    /**
     * Check if user registered via OAuth provider.
     */
    public function isOAuthUser(): bool
    {
        return ! is_null($this->provider);
    }

    /**
     * Get formatted phone number with country code.
     */
    public function getFormattedPhoneAttribute(): ?string
    {
        if (! $this->phone) {
            return null;
        }

        $countryCode = $this->phone_country_code ?? '+95';

        // If phone already includes country code, return as is
        if (str_starts_with($this->phone, '+')) {
            return $this->phone;
        }

        // If phone starts with country code without +, add +
        if (str_starts_with($this->phone, ltrim($countryCode, '+'))) {
            return '+' . $this->phone;
        }

        // Add country code
        return $countryCode . ltrim($this->phone, '0');
    }

    /**
     * Get masked phone number for display.
     */
    public function getMaskedPhoneAttribute(): ?string
    {
        if (! $this->phone) {
            return null;
        }

        $phone = $this->formatted_phone ?? $this->phone;

        if (strlen($phone) <= 4) {
            return str_repeat('*', strlen($phone));
        }

        return str_repeat('*', strlen($phone) - 4) . substr($phone, -4);
    }

    /**
     * Check if two-factor authentication is enabled.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && ! is_null($this->two_factor_secret);
    }

    /**
     * Get the user's average rating.
     */
    public function getAverageRatingAttribute(): float
    {
        return $this->receivedReviews()->avg('rating') ?? 0.0;
    }

    /**
     * Get the total number of reviews received.
     */
    public function getTotalReviewsAttribute(): int
    {
        return $this->receivedReviews()->count();
    }

    /**
     * Get the number of completed jobs.
     */
    public function getJobsCompletedAttribute(): int
    {
        return $this->jobs()->where('status', 'completed')->count();
    }

    /**
     * Get the number of skills offered.
     */
    public function getSkillsOfferedAttribute(): int
    {
        return $this->skills()->where('is_active', true)->count();
    }

    /**
     * Jobs posted by this user.
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    /**
     * Skills offered by this user.
     */
    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    /**
     * Messages sent by this user.
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Messages received by this user.
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'recipient_id');
    }

    /**
     * Payments made by this user.
     */
    public function paymentsMade(): HasMany
    {
        return $this->hasMany(Payment::class, 'payer_id');
    }

    /**
     * Payments received by this user.
     */
    public function paymentsReceived(): HasMany
    {
        return $this->hasMany(Payment::class, 'payee_id');
    }

    /**
     * Reviews given by this user.
     */
    public function reviewsGiven(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    /**
     * Reviews received by this user.
     */
    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    /**
     * Reviews given by this user (alias for compatibility).
     */
    public function givenReviews(): HasMany
    {
        return $this->reviewsGiven();
    }

    /**
     * Reviews received by this user (alias for compatibility).
     */
    public function receivedReviews(): HasMany
    {
        return $this->reviewsReceived();
    }

    /**
     * Jobs assigned to this user.
     */
    public function assignedJobs(): HasMany
    {
        return $this->hasMany(Job::class, 'assigned_to');
    }

    /**
     * Users blocked by this user.
     */
    public function blockedUsers(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocker_id');
    }

    /**
     * Users who have blocked this user.
     */
    public function blockedByUsers(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocked_id');
    }

    /**
     * Rating history for this user.
     */
    public function ratingHistory(): HasMany
    {
        return $this->hasMany(RatingHistory::class);
    }

    /**
     * Saved searches by this user.
     */
    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    /**
     * Scope to filter active users.
     * @param mixed $query
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter admin users.
     * @param mixed $query
     */
    public function scopeAdmins($query)
    {
        return $query->where('is_admin', true);
    }

    /**
     * Scope to search users by name or email.
     * @param mixed $query
     * @param mixed $term
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }

    /**
     * Check if the user account is currently locked.
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Increment failed login attempts and lock account if necessary.
     */
    public function incrementFailedAttempts(string $ipAddress): void
    {
        $maxAttempts = config('auth.lockout.max_attempts', 5);
        $lockoutDuration = (int) config('auth.lockout.lockout_duration', 900);

        $this->increment('failed_login_attempts');
        $this->update(['last_login_ip' => $ipAddress]);

        if ($this->failed_login_attempts >= $maxAttempts) {
            $this->update([
                'locked_until' => now()->addSeconds($lockoutDuration),
            ]);
        }
    }

    /**
     * Reset failed login attempts after successful login.
     */
    public function resetFailedAttempts(string $ipAddress): void
    {
        $loginHistory = $this->login_history ?? [];

        // Keep only last 10 login records
        if (count($loginHistory) >= 10) {
            $loginHistory = array_slice($loginHistory, -9);
        }

        $loginHistory[] = [
            'ip' => $ipAddress,
            'timestamp' => now()->toISOString(),
            'user_agent' => request()->userAgent(),
        ];

        $this->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_ip' => $ipAddress,
            'last_login_at' => now(),
            'login_history' => $loginHistory,
        ]);
    }

    /**
     * Get the time remaining until account unlock.
     */
    public function getLockoutTimeRemaining(): ?int
    {
        if (! $this->isLocked()) {
            return null;
        }

        return max(0, $this->locked_until->diffInSeconds(now()));
    }

    /**
     * Create a new Sanctum token with specific abilities.
     */
    public function createTokenWithAbilities(string $name, array $abilities = ['*'], ?\DateTimeInterface $expiresAt = null): string
    {
        $token = $this->createToken($name, $abilities, $expiresAt);

        return $token->plainTextToken;
    }

    /**
     * Create a token with default abilities based on user role.
     */
    public function createDefaultToken(string $name = 'auth-token'): string
    {
        $abilities = $this->is_admin ? ['*'] : [
            'user:read', 'user:write',
            'jobs:read', 'jobs:write',
            'skills:read', 'skills:write',
            'messages:read', 'messages:write',
            'payments:read', 'payments:write',
            'reviews:read', 'reviews:write',
        ];

        // logger(config('sanctum.expiration'));

        $expiresAt = now()->addMinutes((int) config('sanctum.expiration', 1440));

        return $this->createTokenWithAbilities($name, $abilities, $expiresAt);
    }

    /**
     * Update cached rating statistics.
     */
    public function updateRatingCache(array $stats): void
    {
        $this->update([
            'cached_average_rating' => $stats['simple_average'],
            'cached_weighted_rating' => $stats['weighted_average'],
            'cached_quality_score' => $stats['quality_score'],
            'cached_total_reviews' => $stats['total_reviews'],
            'rating_cache_updated_at' => now(),
            'is_rating_eligible' => $stats['total_reviews'] >= 3,
        ]);
    }

    /**
     * Update last activity timestamp.
     */
    public function updateLastActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Check if rating cache is stale.
     */
    public function isRatingCacheStale(int $maxAgeMinutes = 60): bool
    {
        if (! $this->rating_cache_updated_at) {
            return true;
        }

        return $this->rating_cache_updated_at->diffInMinutes(now()) > $maxAgeMinutes;
    }

    /**
     * Get the user's current rating using cached values or fresh calculation.
     */
    public function getCurrentRating(bool $useCache = true): float
    {
        if ($useCache && ! $this->isRatingCacheStale()) {
            return (float) $this->cached_weighted_rating;
        }

        $ratingService = app(\App\Services\RatingService::class);
        $stats = $ratingService->calculateUserRatingStats($this->id, $useCache);

        if ($useCache) {
            $this->updateRatingCache($stats);
        }

        return $stats['weighted_average'];
    }

    /**
     * Get the user's quality score using cached values or fresh calculation.
     */
    public function getQualityScore(bool $useCache = true): float
    {
        if ($useCache && ! $this->isRatingCacheStale()) {
            return (float) $this->cached_quality_score;
        }

        $ratingService = app(\App\Services\RatingService::class);
        $stats = $ratingService->calculateUserRatingStats($this->id, $useCache);

        if ($useCache) {
            $this->updateRatingCache($stats);
        }

        return $stats['quality_score'];
    }

    /**
     * Check if user is eligible for rating-based features.
     */
    public function isRatingEligible(): bool
    {
        return $this->is_rating_eligible && $this->cached_total_reviews >= 3;
    }

    /**
     * Get user's ranking in their category or overall.
     */
    public function getRanking(int $categoryId = null): array
    {
        $ratingService = app(\App\Services\RatingService::class);

        return $ratingService->getUserRanking($this->id, $categoryId);
    }

    /**
     * Scope to filter users eligible for rating.
     * @param mixed $query
     */
    public function scopeRatingEligible($query)
    {
        return $query->where('is_rating_eligible', true)
            ->where('cached_total_reviews', '>=', 3);
    }

    /**
     * Scope to order by rating quality.
     * @param mixed $query
     */
    public function scopeOrderByRating($query, string $direction = 'desc')
    {
        return $query->orderBy('cached_weighted_rating', $direction);
    }

    /**
     * Scope to order by quality score.
     * @param mixed $query
     */
    public function scopeOrderByQuality($query, string $direction = 'desc')
    {
        return $query->orderBy('cached_quality_score', $direction);
    }

    /**
     * Scope to filter by minimum rating.
     * @param mixed $query
     */
    public function scopeMinimumRating($query, float $minRating)
    {
        return $query->where('cached_weighted_rating', '>=', $minRating);
    }

    /**
     * Scope to filter recently active users.
     * @param mixed $query
     */
    public function scopeRecentlyActive($query, int $days = 30)
    {
        return $query->where('last_activity_at', '>=', now()->subDays($days));
    }

    /**
     * Check if user can perform a specific action.
     * @param mixed $abilities
     * @param mixed $arguments
     */
    public function can($abilities, $arguments = [])
    {
        // Admin users can do everything
        if ($this->is_admin) {
            return true;
        }

        // Handle single ability string
        if (is_string($abilities)) {
            $ability = $abilities;
        } else {
            // For array of abilities, check if user can perform any of them
            if (is_array($abilities)) {
                foreach ($abilities as $ability) {
                    if ($this->can($ability, $arguments)) {
                        return true;
                    }
                }

                return false;
            }

            return false;
        }

        // Define abilities for regular users
        $userAbilities = [
            'view-analytics' => false, // Only admins can view analytics
            'manage-analytics' => false, // Only admins can manage analytics
            'user:read' => true,
            'user:write' => true,
            'jobs:read' => true,
            'jobs:write' => true,
            'skills:read' => true,
            'skills:write' => true,
            'messages:read' => true,
            'messages:write' => true,
            'payments:read' => true,
            'payments:write' => true,
            'reviews:read' => true,
            'reviews:write' => true,
        ];

        return $userAbilities[$ability] ?? false;
    }

    /**
     * Check if user cannot perform a specific action.
     * @param mixed $abilities
     * @param mixed $arguments
     */
    public function cannot($abilities, $arguments = [])
    {
        return ! $this->can($abilities, $arguments);
    }

    /**
     * Get the average rating for this user.
     */
    public function averageRating(): float
    {
        return $this->receivedReviews()->avg('rating') ?? 0.0;
    }

    /**
     * Get the count of completed jobs for this user.
     */
    public function jobsCompletedCount(): int
    {
        return $this->assignedJobs()->where('status', 'completed')->count();
    }

    /**
     * Get the total earnings for this user.
     */
    public function totalEarnings(): float
    {
        return $this->paymentsReceived()->where('status', 'released')->sum('amount') ?? 0.0;
    }

    /**
     * Block another user.
     */
    public function blockUser(int $userId): void
    {
        if ($userId === $this->id) {
            throw new \InvalidArgumentException('Cannot block yourself');
        }

        UserBlock::firstOrCreate([
            'blocker_id' => $this->id,
            'blocked_id' => $userId,
        ]);
    }

    /**
     * Unblock another user.
     */
    public function unblockUser(int $userId): void
    {
        UserBlock::where([
            'blocker_id' => $this->id,
            'blocked_id' => $userId,
        ])->delete();
    }

    /**
     * Check if this user has blocked another user.
     */
    public function hasBlocked(int $userId): bool
    {
        return UserBlock::where([
            'blocker_id' => $this->id,
            'blocked_id' => $userId,
        ])->exists();
    }

    /**
     * GDPR requests made by this user.
     */
    public function gdprRequests(): HasMany
    {
        return $this->hasMany(GdprRequest::class);
    }

    /**
     * GDPR requests processed by this admin user.
     */
    public function processedGdprRequests(): HasMany
    {
        return $this->hasMany(GdprRequest::class, 'processed_by');
    }

    /**
     * Security incidents affecting this user.
     */
    public function securityIncidents(): HasMany
    {
        return $this->hasMany(SecurityIncident::class, 'affected_user_id');
    }

    /**
     * Security incidents assigned to this admin user.
     */
    public function assignedSecurityIncidents(): HasMany
    {
        return $this->hasMany(SecurityIncident::class, 'assigned_to');
    }

    /**
     * Audit logs for this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'phone_verification_code_expires_at' => 'datetime',
            'phone_verification_locked_until' => 'datetime',
            'provider_data' => 'array',
            'two_factor_recovery_codes' => 'array',
            'is_active' => 'boolean',
            'is_admin' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'rating_cache_updated_at' => 'datetime',
            'login_history' => 'array',
            'cached_average_rating' => 'decimal:2',
            'cached_weighted_rating' => 'decimal:2',
            'cached_quality_score' => 'decimal:2',
            'cached_total_reviews' => 'integer',
            'is_rating_eligible' => 'boolean',
        ];
    }
}
