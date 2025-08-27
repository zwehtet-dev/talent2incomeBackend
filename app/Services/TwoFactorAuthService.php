<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TwoFactorAuthService
{
    /**
     * Generate a two-factor authentication code.
     */
    public function generateCode(User $user): string
    {
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Store the code in cache for 10 minutes
        Cache::put(
            $this->getCacheKey($user),
            $code,
            now()->addMinutes(10)
        );

        return $code;
    }

    /**
     * Verify a two-factor authentication code.
     */
    public function verifyCode(User $user, string $code): bool
    {
        $storedCode = Cache::get($this->getCacheKey($user));

        if (! $storedCode || $storedCode !== $code) {
            return false;
        }

        // Clear the code after successful verification
        Cache::forget($this->getCacheKey($user));

        return true;
    }

    /**
     * Check if a user has a pending two-factor code.
     */
    public function hasPendingCode(User $user): bool
    {
        return Cache::has($this->getCacheKey($user));
    }

    /**
     * Clear any pending two-factor code for a user.
     */
    public function clearCode(User $user): void
    {
        Cache::forget($this->getCacheKey($user));
    }

    /**
     * Generate a backup recovery code.
     */
    public function generateRecoveryCode(): string
    {
        return Str::upper(Str::random(8));
    }

    /**
     * Generate multiple backup recovery codes.
     * @return array<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->generateRecoveryCode();
        }

        return $codes;
    }

    /**
     * Get the cache key for a user's two-factor code.
     */
    private function getCacheKey(User $user): string
    {
        return "2fa_code_{$user->id}";
    }
}
