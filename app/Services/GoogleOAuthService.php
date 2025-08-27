<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleOAuthService
{
    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        try {
            return Socialite::driver('google')
                ->scopes(['openid', 'profile', 'email'])
                ->with([
                    'access_type' => 'offline',
                    'prompt' => 'consent select_account',
                ])
                ->redirect();
        } catch (\Exception $e) {
            Log::error('Google OAuth redirect failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \Exception('Failed to initiate Google OAuth. Please try again.');
        }
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback(): array
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // Validate required Google user data
            if (! $googleUser->getEmail()) {
                Log::warning('Google OAuth callback missing email', [
                    'google_id' => $googleUser->getId(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Email address is required for registration. Please ensure your Google account has a verified email.',
                    'error_code' => 'MISSING_EMAIL',
                ];
            }

            // Check if user already exists with this Google ID
            $existingUser = User::where('google_id', $googleUser->getId())->first();

            if ($existingUser) {
                return $this->handleExistingGoogleUser($existingUser, $googleUser);
            }

            // Check if user exists with the same email
            $emailUser = User::where('email', strtolower($googleUser->getEmail()))->first();

            if ($emailUser) {
                return $this->linkGoogleToExistingUser($emailUser, $googleUser);
            }

            // Create new user
            return $this->createNewGoogleUser($googleUser);

        } catch (InvalidStateException $e) {
            Log::warning('Google OAuth invalid state', [
                'error' => $e->getMessage(),
                'ip' => request()->ip(),
            ]);

            return [
                'success' => false,
                'message' => 'Invalid OAuth state. Please try signing in again.',
                'error_code' => 'INVALID_STATE',
            ];

        } catch (\Exception $e) {
            Log::error('Google OAuth callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => request()->ip(),
            ]);

            return [
                'success' => false,
                'message' => 'Google sign-in failed. Please try again.',
                'error_code' => 'OAUTH_CALLBACK_FAILED',
            ];
        }
    }

    /**
     * Unlink Google account from user
     */
    public function unlinkGoogleAccount(User $user): array
    {
        try {
            // Check if user has a password set (for security)
            if (! $user->password || $user->password === Hash::make('')) {
                return [
                    'success' => false,
                    'message' => 'Please set a password before unlinking your Google account.',
                    'error_code' => 'PASSWORD_REQUIRED',
                ];
            }

            // Unlink Google account
            $user->update([
                'google_id' => null,
                'provider' => null,
                'provider_data' => null,
            ]);

            Log::info('Google account unlinked', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return [
                'success' => true,
                'message' => 'Google account unlinked successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Error unlinking Google account', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to unlink Google account. Please try again.',
                'error_code' => 'UNLINK_FAILED',
            ];
        }
    }

    /**
     * Check if user has Google account linked
     */
    public function hasGoogleLinked(User $user): bool
    {
        return ! is_null($user->google_id);
    }

    /**
     * Get OAuth status for user
     */
    public function getOAuthStatus(User $user): array
    {
        return [
            'google_linked' => $this->hasGoogleLinked($user),
            'provider' => $user->provider,
            'linked_at' => $user->provider_data['updated_at'] ?? null,
            'can_unlink' => $this->hasGoogleLinked($user) && ! is_null($user->password),
        ];
    }

    /**
     * Handle existing Google user login
     * @param mixed $googleUser
     * @return array<string, mixed>
     */
    private function handleExistingGoogleUser(User $user, $googleUser): array
    {
        try {
            // Check if account is active
            if (! $user->is_active) {
                Log::warning('Google OAuth attempt on inactive account', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'google_id' => $googleUser->getId(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact support.',
                    'error_code' => 'ACCOUNT_INACTIVE',
                ];
            }

            // Update user data from Google (in case profile changed)
            $this->updateUserFromGoogle($user, $googleUser);

            // Generate authentication token
            $token = $user->createDefaultToken('google-oauth-token');

            // Log successful Google login
            Log::info('Successful Google OAuth login', [
                'user_id' => $user->id,
                'email' => $user->email,
                'google_id' => $googleUser->getId(),
                'ip' => request()->ip(),
            ]);

            return [
                'success' => true,
                'message' => 'Successfully signed in with Google',
                'user' => $this->formatUserResponse($user),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => now()->addMinutes(config('sanctum.expiration', 1440))->toDateTimeString(),
                'is_new_user' => false,
            ];

        } catch (\Exception $e) {
            Log::error('Error handling existing Google user', [
                'user_id' => $user->id,
                'google_id' => $googleUser->getId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to sign in. Please try again.',
                'error_code' => 'LOGIN_FAILED',
            ];
        }
    }

    /**
     * Link Google account to existing user
     * @param mixed $googleUser
     * @return array<string, mixed>
     */
    private function linkGoogleToExistingUser(User $user, $googleUser): array
    {
        try {
            // Check if account is active
            if (! $user->is_active) {
                return [
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact support.',
                    'error_code' => 'ACCOUNT_INACTIVE',
                ];
            }

            // Link Google account to existing user
            $user->update([
                'google_id' => $googleUser->getId(),
                'provider' => 'google',
                'provider_data' => $this->getGoogleProviderData($googleUser),
                'email_verified_at' => $user->email_verified_at ?? now(), // Mark as verified if not already
            ]);

            // Generate authentication token
            $token = $user->createDefaultToken('google-oauth-token');

            Log::info('Google account linked to existing user', [
                'user_id' => $user->id,
                'email' => $user->email,
                'google_id' => $googleUser->getId(),
                'ip' => request()->ip(),
            ]);

            return [
                'success' => true,
                'message' => 'Google account linked successfully. You can now sign in with Google.',
                'user' => $this->formatUserResponse($user),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => now()->addMinutes(config('sanctum.expiration', 1440))->toDateTimeString(),
                'is_new_user' => false,
                'account_linked' => true,
            ];

        } catch (\Exception $e) {
            Log::error('Error linking Google to existing user', [
                'user_id' => $user->id,
                'google_id' => $googleUser->getId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to link Google account. Please try again.',
                'error_code' => 'LINK_FAILED',
            ];
        }
    }

    /**
     * Create new user from Google OAuth
     * @param mixed $googleUser
     * @return array<string, mixed>
     */
    private function createNewGoogleUser($googleUser): array
    {
        try {
            // Extract name parts
            $nameParts = $this->extractNameParts($googleUser->getName());

            // Create new user
            $user = User::create([
                'first_name' => $nameParts['first_name'],
                'last_name' => $nameParts['last_name'],
                'email' => strtolower($googleUser->getEmail()),
                'password' => Hash::make(Str::random(32)), // Random password since they'll use OAuth
                'google_id' => $googleUser->getId(),
                'provider' => 'google',
                'provider_data' => $this->getGoogleProviderData($googleUser),
                'email_verified_at' => now(), // Google emails are pre-verified
                'avatar' => $googleUser->getAvatar(),
                'is_active' => true,
            ]);

            // Generate authentication token
            $token = $user->createDefaultToken('google-oauth-token');

            Log::info('New user created via Google OAuth', [
                'user_id' => $user->id,
                'email' => $user->email,
                'google_id' => $googleUser->getId(),
                'ip' => request()->ip(),
            ]);

            return [
                'success' => true,
                'message' => 'Account created successfully with Google',
                'user' => $this->formatUserResponse($user),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => now()->addMinutes(config('sanctum.expiration', 1440))->toDateTimeString(),
                'is_new_user' => true,
                'requires_phone_verification' => true,
            ];

        } catch (\Exception $e) {
            Log::error('Error creating new Google user', [
                'google_id' => $googleUser->getId(),
                'email' => $googleUser->getEmail(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create account. Please try again.',
                'error_code' => 'USER_CREATION_FAILED',
            ];
        }
    }

    /**
     * Update user data from Google
     * @param mixed $googleUser
     */
    private function updateUserFromGoogle(User $user, $googleUser): void
    {
        $updateData = [
            'provider_data' => $this->getGoogleProviderData($googleUser),
        ];

        // Update avatar if user doesn't have one or if Google avatar is newer
        if (! $user->avatar || $googleUser->getAvatar()) {
            $updateData['avatar'] = $googleUser->getAvatar();
        }

        // Update name if user hasn't customized it
        if ($this->shouldUpdateNameFromGoogle($user, $googleUser)) {
            $nameParts = $this->extractNameParts($googleUser->getName());
            $updateData['first_name'] = $nameParts['first_name'];
            $updateData['last_name'] = $nameParts['last_name'];
        }

        $user->update($updateData);
    }

    /**
     * Check if we should update name from Google
     * @param mixed $googleUser
     */
    private function shouldUpdateNameFromGoogle(User $user, $googleUser): bool
    {
        // Don't update if user has customized their name significantly
        $currentFullName = trim($user->first_name . ' ' . $user->last_name);
        $googleFullName = trim($googleUser->getName());

        // If names are very different, user probably customized it
        return similar_text(strtolower($currentFullName), strtolower($googleFullName)) > 0.8;
    }

    /**
     * Extract first and last name from full name
     * @return array<string, string>
     */
    private function extractNameParts(?string $fullName): array
    {
        if (! $fullName) {
            return ['first_name' => 'User', 'last_name' => ''];
        }

        $parts = explode(' ', trim($fullName), 2);

        return [
            'first_name' => $parts[0] ?? 'User',
            'last_name' => $parts[1] ?? '',
        ];
    }

    /**
     * Get Google provider data to store
     * @param mixed $googleUser
     * @return array<string, mixed>
     */
    private function getGoogleProviderData($googleUser): array
    {
        return [
            'id' => $googleUser->getId(),
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'avatar' => $googleUser->getAvatar(),
            'email_verified' => true, // Google emails are verified
            'locale' => $googleUser->user['locale'] ?? null,
            'picture' => $googleUser->user['picture'] ?? null,
            'given_name' => $googleUser->user['given_name'] ?? null,
            'family_name' => $googleUser->user['family_name'] ?? null,
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Format user response for API
     * @return array<string, mixed>
     */
    private function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toDateTimeString(),
            'avatar' => $user->avatar,
            'phone' => $user->phone,
            'phone_verified_at' => $user->phone_verified_at?->toDateTimeString(),
            'provider' => $user->provider,
            'is_admin' => $user->is_admin,
            'created_at' => $user->created_at->toDateTimeString(),
        ];
    }
}
