<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

class SmsVerificationService
{
    private Client $twilio;
    private string $fromNumber;
    private int $codeLength;
    private int $codeExpiryMinutes;
    private int $maxAttempts;
    private int $lockoutMinutes;

    public function __construct()
    {
        $this->twilio = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );

        $this->fromNumber = config('services.twilio.from');
        $this->codeLength = config('sms.verification_code_length', 6);
        $this->codeExpiryMinutes = config('sms.code_expiry_minutes', 10);
        $this->maxAttempts = config('sms.max_attempts', 3);
        $this->lockoutMinutes = config('sms.lockout_minutes', 30);
    }

    /**
     * Send SMS verification code to Myanmar phone number
     * @return array<string, mixed>
     */
    public function sendVerificationCode(User $user, string $phoneNumber): array
    {
        try {
            // Validate Myanmar phone number format
            $formattedPhone = $this->formatMyanmarPhoneNumber($phoneNumber);
            if (! $formattedPhone) {
                return [
                    'success' => false,
                    'message' => 'Invalid Myanmar phone number format. Please use format: 09xxxxxxxx or +959xxxxxxxx',
                    'error_code' => 'INVALID_PHONE_FORMAT',
                ];
            }

            // Check if user is locked out
            if ($this->isUserLockedOut($user)) {
                $lockoutRemaining = $this->getLockoutTimeRemaining($user);

                return [
                    'success' => false,
                    'message' => "Too many verification attempts. Please try again in {$lockoutRemaining} minutes.",
                    'error_code' => 'USER_LOCKED_OUT',
                    'lockout_remaining_minutes' => $lockoutRemaining,
                ];
            }

            // Check rate limiting per phone number
            if ($this->isPhoneRateLimited($formattedPhone)) {
                return [
                    'success' => false,
                    'message' => 'Too many SMS requests for this phone number. Please try again later.',
                    'error_code' => 'PHONE_RATE_LIMITED',
                ];
            }

            // Generate verification code
            $code = $this->generateVerificationCode();

            // Store verification code in database
            $user->update([
                'phone' => $phoneNumber,
                'phone_country_code' => '+95',
                'phone_verification_code' => $code,
                'phone_verification_code_expires_at' => now()->addMinutes($this->codeExpiryMinutes),
                'phone_verification_attempts' => 0,
            ]);

            // Send SMS via Twilio
            $message = $this->twilio->messages->create(
                $formattedPhone,
                [
                    'from' => $this->fromNumber,
                    'body' => $this->getVerificationMessage($code, $user->first_name),
                ]
            );

            // Set rate limiting for phone number
            $this->setPhoneRateLimit($formattedPhone);

            // Log successful SMS send
            Log::info('SMS verification code sent', [
                'user_id' => $user->id,
                'phone' => $this->maskPhoneNumber($formattedPhone),
                'message_sid' => $message->sid,
                'status' => $message->status,
            ]);

            return [
                'success' => true,
                'message' => 'Verification code sent successfully',
                'expires_in_minutes' => $this->codeExpiryMinutes,
                'phone_masked' => $this->maskPhoneNumber($formattedPhone),
            ];

        } catch (TwilioException $e) {
            Log::error('Twilio SMS sending failed', [
                'user_id' => $user->id,
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send verification code. Please try again.',
                'error_code' => 'SMS_SEND_FAILED',
            ];

        } catch (\Exception $e) {
            Log::error('SMS verification service error', [
                'user_id' => $user->id,
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Verification service temporarily unavailable. Please try again.',
                'error_code' => 'SERVICE_ERROR',
            ];
        }
    }

    /**
     * Verify SMS code
     * @return array<string, mixed>
     */
    public function verifyCode(User $user, string $code): array
    {
        try {
            // Check if user is locked out
            if ($this->isUserLockedOut($user)) {
                $lockoutRemaining = $this->getLockoutTimeRemaining($user);

                return [
                    'success' => false,
                    'message' => "Too many verification attempts. Please try again in {$lockoutRemaining} minutes.",
                    'error_code' => 'USER_LOCKED_OUT',
                    'lockout_remaining_minutes' => $lockoutRemaining,
                ];
            }

            // Check if code exists and hasn't expired
            if (! $user->phone_verification_code ||
                ! $user->phone_verification_code_expires_at ||
                now()->isAfter($user->phone_verification_code_expires_at)) {

                return [
                    'success' => false,
                    'message' => 'Verification code has expired. Please request a new code.',
                    'error_code' => 'CODE_EXPIRED',
                ];
            }

            // Verify the code
            if ($user->phone_verification_code !== $code) {
                // Increment failed attempts
                $attempts = $user->phone_verification_attempts + 1;
                $updateData = ['phone_verification_attempts' => $attempts];

                // Lock user if max attempts reached
                if ($attempts >= $this->maxAttempts) {
                    $updateData['phone_verification_locked_until'] = now()->addMinutes($this->lockoutMinutes);
                    $updateData['phone_verification_code'] = null; // Clear the code
                    $updateData['phone_verification_code_expires_at'] = null;
                }

                $user->update($updateData);

                Log::warning('Invalid SMS verification code attempt', [
                    'user_id' => $user->id,
                    'phone' => $this->maskPhoneNumber($user->phone),
                    'attempts' => $attempts,
                    'locked' => $attempts >= $this->maxAttempts,
                ]);

                if ($attempts >= $this->maxAttempts) {
                    return [
                        'success' => false,
                        'message' => 'Too many invalid attempts. Account locked for 30 minutes.',
                        'error_code' => 'MAX_ATTEMPTS_EXCEEDED',
                        'lockout_minutes' => $this->lockoutMinutes,
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Invalid verification code.',
                    'error_code' => 'INVALID_CODE',
                    'attempts_remaining' => $this->maxAttempts - $attempts,
                ];
            }

            // Code is valid - mark phone as verified
            $user->update([
                'phone_verified_at' => now(),
                'phone_verification_code' => null,
                'phone_verification_code_expires_at' => null,
                'phone_verification_attempts' => 0,
                'phone_verification_locked_until' => null,
            ]);

            Log::info('Phone number verified successfully', [
                'user_id' => $user->id,
                'phone' => $this->maskPhoneNumber($user->phone),
            ]);

            return [
                'success' => true,
                'message' => 'Phone number verified successfully',
                'verified_at' => $user->phone_verified_at->toDateTimeString(),
            ];

        } catch (\Exception $e) {
            Log::error('SMS verification error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Verification service temporarily unavailable. Please try again.',
                'error_code' => 'SERVICE_ERROR',
            ];
        }
    }

    /**
     * Check if phone number is verified
     */
    public function isPhoneVerified(User $user): bool
    {
        return ! is_null($user->phone_verified_at);
    }

    /**
     * Clear verification code (for security)
     */
    public function clearVerificationCode(User $user): void
    {
        $user->update([
            'phone_verification_code' => null,
            'phone_verification_code_expires_at' => null,
            'phone_verification_attempts' => 0,
        ]);
    }

    /**
     * Get verification status
     * @return array<string, mixed>
     */
    public function getVerificationStatus(User $user): array
    {
        return [
            'phone_verified' => $this->isPhoneVerified($user),
            'phone_verified_at' => $user->phone_verified_at?->toDateTimeString(),
            'phone_masked' => $user->phone ? $this->maskPhoneNumber($user->phone) : null,
            'is_locked_out' => $this->isUserLockedOut($user),
            'lockout_remaining_minutes' => $this->getLockoutTimeRemaining($user),
            'has_pending_code' => ! is_null($user->phone_verification_code) &&
                                 $user->phone_verification_code_expires_at &&
                                 now()->isBefore($user->phone_verification_code_expires_at),
            'code_expires_at' => $user->phone_verification_code_expires_at?->toDateTimeString(),
        ];
    }

    /**
     * Format Myanmar phone number to international format
     */
    private function formatMyanmarPhoneNumber(string $phoneNumber): ?string
    {
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Myanmar phone number patterns:
        // 09xxxxxxxx (local format) -> +959xxxxxxxx
        // 959xxxxxxxx (without +) -> +959xxxxxxxx
        // +959xxxxxxxx (international format) -> +959xxxxxxxx

        if (preg_match('/^09[0-9]{8}$/', $cleaned)) {
            // Local format: 09xxxxxxxx -> +959xxxxxxxx
            return '+95' . substr($cleaned, 1);
        } elseif (preg_match('/^959[0-9]{8}$/', $cleaned)) {
            // Without +: 959xxxxxxxx -> +959xxxxxxxx
            return '+' . $cleaned;
        } elseif (preg_match('/^959[0-9]{8}$/', $phoneNumber)) {
            // Already in international format
            return $phoneNumber;
        }

        return null; // Invalid format
    }

    /**
     * Generate random verification code
     */
    private function generateVerificationCode(): string
    {
        return str_pad((string) random_int(0, pow(10, $this->codeLength) - 1), $this->codeLength, '0', STR_PAD_LEFT);
    }

    /**
     * Get verification SMS message
     */
    private function getVerificationMessage(string $code, ?string $firstName = null): string
    {
        $greeting = $firstName ? "Hi {$firstName}," : 'Hello,';

        return "{$greeting} Your Talent2Income verification code is: {$code}. This code will expire in {$this->codeExpiryMinutes} minutes. Do not share this code with anyone.";
    }

    /**
     * Check if user is locked out from phone verification
     */
    private function isUserLockedOut(User $user): bool
    {
        return $user->phone_verification_locked_until &&
               now()->isBefore($user->phone_verification_locked_until);
    }

    /**
     * Get remaining lockout time in minutes
     */
    private function getLockoutTimeRemaining(User $user): int
    {
        if (! $this->isUserLockedOut($user)) {
            return 0;
        }

        return (int) ceil($user->phone_verification_locked_until->diffInMinutes(now()));
    }

    /**
     * Check if phone number is rate limited
     */
    private function isPhoneRateLimited(string $phoneNumber): bool
    {
        $key = 'sms_rate_limit:' . hash('sha256', $phoneNumber);

        return Cache::has($key);
    }

    /**
     * Set rate limit for phone number
     */
    private function setPhoneRateLimit(string $phoneNumber): void
    {
        $key = 'sms_rate_limit:' . hash('sha256', $phoneNumber);
        $rateLimitMinutes = config('sms.rate_limit_minutes', 2);
        Cache::put($key, true, now()->addMinutes($rateLimitMinutes));
    }

    /**
     * Mask phone number for logging (show only last 4 digits)
     */
    private function maskPhoneNumber(string $phoneNumber): string
    {
        if (strlen($phoneNumber) <= 4) {
            return str_repeat('*', strlen($phoneNumber));
        }

        return str_repeat('*', strlen($phoneNumber) - 4) . substr($phoneNumber, -4);
    }
}
