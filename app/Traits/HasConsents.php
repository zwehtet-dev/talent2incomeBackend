<?php

namespace App\Traits;

use App\Models\UserConsent;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasConsents
{
    /**
     * Get all consents for this user.
     * @return HasMany<UserConsent>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class, 'user_id');
    }

    /**
     * Get active consents for this user.
     * @return HasMany<UserConsent>
     */
    public function activeConsents(): HasMany
    {
        return $this->consents()->active();
    }

    /**
     * Check if user has given consent for a specific type.
     */
    public function hasConsent(string $consentType): bool
    {
        $consent = UserConsent::getCurrentConsent($this->id, $consentType);

        return $consent && $consent->isActive();
    }

    /**
     * Check if user has all required consents.
     */
    public function hasRequiredConsents(): bool
    {
        return UserConsent::hasRequiredConsents($this->id);
    }

    /**
     * Grant consent for a specific type.
     * @param array<string, mixed> $metadata
     */
    public function grantConsent(
        string $consentType,
        string $consentVersion,
        array $metadata = []
    ): UserConsent {
        return UserConsent::create([
            'user_id' => $this->id,
            'consent_type' => $consentType,
            'consent_version' => $consentVersion,
            'is_granted' => true,
            'granted_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'consent_method' => 'api',
            'metadata' => $metadata,
        ]);
    }

    /**
     * Withdraw consent for a specific type.
     */
    public function withdrawConsent(string $consentType, string $reason = null): bool
    {
        $consent = UserConsent::getCurrentConsent($this->id, $consentType);

        if ($consent && $consent->isActive()) {
            $consent->withdraw($reason);

            return true;
        }

        return false;
    }

    /**
     * Get consent status for all types.
     * @return array<string, array<string, mixed>>
     */
    public function getConsentStatus(): array
    {
        $consents = $this->consents()->get()->groupBy('consent_type');
        $status = [];

        $consentTypes = [
            UserConsent::TYPE_PRIVACY_POLICY,
            UserConsent::TYPE_TERMS_OF_SERVICE,
            UserConsent::TYPE_MARKETING,
            UserConsent::TYPE_COOKIES,
            UserConsent::TYPE_DATA_PROCESSING,
            UserConsent::TYPE_THIRD_PARTY_SHARING,
        ];

        foreach ($consentTypes as $type) {
            $latestConsent = $consents->get($type)?->first();
            $status[$type] = [
                'granted' => $latestConsent && $latestConsent->isActive(),
                'granted_at' => $latestConsent?->granted_at,
                'version' => $latestConsent?->consent_version,
                'withdrawn_at' => $latestConsent?->withdrawn_at,
            ];
        }

        return $status;
    }

    /**
     * Scope to get users with specific consent.
     * @param mixed $query
     * @return mixed
     */
    public function scopeWithConsent($query, string $consentType)
    {
        return $query->whereHas('consents', function ($q) use ($consentType) {
            $q->where('consent_type', $consentType)
                ->where('is_granted', true)
                ->whereNull('withdrawn_at');
        });
    }

    /**
     * Scope to get users without specific consent.
     * @param mixed $query
     * @return mixed
     */
    public function scopeWithoutConsent($query, string $consentType)
    {
        return $query->whereDoesntHave('consents', function ($q) use ($consentType) {
            $q->where('consent_type', $consentType)
                ->where('is_granted', true)
                ->whereNull('withdrawn_at');
        });
    }
}
