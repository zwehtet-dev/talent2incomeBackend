<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    protected TwoFactorAuthService $twoFactorService;

    public function __construct(TwoFactorAuthService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     tags={"Authentication"},
     *     summary="Register a new user account",
     *     description="Create a new user account with email verification",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name", "last_name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="first_name", type="string", maxLength=100, example="John"),
     *             @OA\Property(property="last_name", type="string", maxLength=100, example="Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", minLength=8, example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Registration successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Registration successful. Please verify your email."),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="email_verified_at", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     type="array",
     *                     @OA\Items(type="string", example="The email has already been taken.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many requests",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Too Many Attempts.")
     *         )
     *     )
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            // Create user with validated data
            $user = User::create([
                'first_name' => $request->validated('first_name'),
                'last_name' => $request->validated('last_name'),
                'email' => strtolower($request->validated('email')),
                'password' => $request->validated('password'),
            ]);

            // Send email verification (except in testing)
            if (! app()->environment('testing')) {
                event(new Registered($user));
            }

            // Log successful registration
            Log::channel('auth')->info('User registered successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString(),
            ]);

            // Create token for immediate use
            $token = $user->createToken('api')->plainTextToken;

            return response()->json([
                'message' => 'Registration successful. Please check your email to verify your account.',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                ],
                'token' => $token,
                'requires_verification' => true,
            ], 201);

        } catch (\Exception $e) {
            Log::channel('auth')->error('Registration failed', [
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Registration failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"Authentication"},
     *     summary="Authenticate user and return access token",
     *     description="Login with email and password to receive a Sanctum access token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="email_verified_at", type="string", example="2024-01-01T00:00:00Z")
     *             ),
     *             @OA\Property(property="token", type="string", example="1|abc123..."),
     *             @OA\Property(property="expires_at", type="string", example="2024-01-01T00:00:00Z")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid credentials")
     *         )
     *     ),
     *     @OA\Response(
     *         response=423,
     *         description="Account locked",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Account temporarily locked due to too many failed attempts")
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many requests",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Too Many Attempts.")
     *         )
     *     )
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $email = strtolower($request->validated('email'));
            $password = $request->validated('password');
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();

            // Find user by email
            $user = User::where('email', $email)->first();

            if (! $user) {
                // Increment rate limiting for non-existent emails
                $request->incrementLoginAttempts();

                Log::channel('auth')->warning('Login attempt with non-existent email', [
                    'email' => $email,
                    'ip' => $ipAddress,
                    'user_agent' => $userAgent,
                    'timestamp' => now()->toISOString(),
                ]);

                return response()->json([
                    'message' => 'Invalid credentials',
                ], 401);
            }

            // Check if account is locked due to failed attempts
            if ($user->isLocked()) {
                $timeRemaining = $user->getLockoutTimeRemaining();

                Log::channel('auth')->warning('Login attempt on locked account', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $ipAddress,
                    'user_agent' => $userAgent,
                    'time_remaining' => $timeRemaining,
                    'timestamp' => now()->toISOString(),
                ]);

                return response()->json([
                    'message' => 'Account is temporarily locked due to too many failed login attempts.',
                    'locked_until' => $user->locked_until->toISOString(),
                    'time_remaining_seconds' => $timeRemaining,
                ], 423);
            }

            // Check if account is active
            if (! $user->is_active) {
                Log::channel('auth')->warning('Login attempt on inactive account', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $ipAddress,
                    'user_agent' => $userAgent,
                    'timestamp' => now()->toISOString(),
                ]);

                return response()->json([
                    'message' => 'Account is inactive. Please contact support.',
                ], 403);
            }

            // Verify password
            if (! Hash::check($password, $user->password)) {
                // Increment both user-specific and request-specific failed attempts
                $user->incrementFailedAttempts($ipAddress);
                $request->incrementLoginAttempts();

                Log::channel('auth')->warning('Failed login attempt - invalid password', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $ipAddress,
                    'user_agent' => $userAgent,
                    'failed_attempts' => $user->failed_login_attempts,
                    'timestamp' => now()->toISOString(),
                ]);

                return response()->json([
                    'message' => 'Invalid credentials',
                ], 401);
            }

            // Check if email is verified (optional based on config)
            if (config('auth.require_email_verification', true) && ! $user->hasVerifiedEmail()) {
                Log::channel('auth')->info('Login attempt with unverified email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $ipAddress,
                    'timestamp' => now()->toISOString(),
                ]);

                return response()->json([
                    'message' => 'Please verify your email address before logging in.',
                    'requires_verification' => true,
                ], 403);
            }

            // Successful authentication - reset failed attempts and clear rate limiting
            $user->resetFailedAttempts($ipAddress);
            $request->clearLoginAttempts();

            // Create authentication token with appropriate abilities
            $token = $user->createDefaultToken('auth-token');

            // Log successful login
            Log::channel('auth')->info('Successful login', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $ipAddress,
                'user_agent' => $userAgent,
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at?->toISOString(),
                    'is_admin' => $user->is_admin,
                    'avatar' => $user->avatar,
                    'last_login_at' => $user->last_login_at?->toISOString(),
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => now()->addMinutes((int) config('sanctum.expiration', 1440))->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::channel('auth')->error('Login process failed', [
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Login failed. Please try again.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     tags={"Authentication"},
     *     summary="Logout user and revoke current access token",
     *     description="Revoke the current access token and log the user out",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $token = $request->user()->currentAccessToken();

            // Get token info before deletion for logging
            $tokenName = $token->name;
            $tokenId = $token->id;

            // Revoke current token
            $token->delete();

            // Clear any pending 2FA codes
            $this->twoFactorService->clearCode($user);

            Log::channel('auth')->info('User logged out successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'token_id' => $tokenId,
                'token_name' => $tokenName,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Logged out successfully',
            ]);

        } catch (\Exception $e) {
            Log::channel('auth')->error('Logout failed', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Logout failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Logout from all devices by revoking all user tokens.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $tokenCount = $user->tokens()->count();

            // Revoke all tokens for the user
            $user->tokens()->delete();

            // Clear any pending 2FA codes
            $this->twoFactorService->clearCode($user);

            Log::channel('auth')->info('User logged out from all devices', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tokens_revoked' => $tokenCount,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Logged out from all devices successfully',
                'tokens_revoked' => $tokenCount,
            ]);

        } catch (\Exception $e) {
            Log::channel('auth')->error('Logout all failed', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Logout from all devices failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Send password reset link with secure token generation and rate limiting.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $email = strtolower($request->validated('email'));

            // Send password reset link
            $status = Password::sendResetLink(['email' => $email]);

            Log::channel('auth')->info('Password reset requested', [
                'email' => $email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => $status,
                'timestamp' => now()->toISOString(),
            ]);

            // Always return success message to prevent email enumeration
            return response()->json([
                'message' => 'If an account with that email exists, we have sent a password reset link.',
            ]);

        } catch (\Exception $e) {
            Log::channel('auth')->error('Password reset request failed', [
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Unable to process password reset request. Please try again.',
            ], 500);
        }
    }

    /**
     * Reset password with secure token validation.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $credentials = $request->only('email', 'password', 'password_confirmation', 'token');
            $credentials['email'] = strtolower($credentials['email']);

            $status = Password::reset(
                $credentials,
                function (User $user, string $password) use ($request) {
                    $user->forceFill([
                        'password' => $password,
                        'remember_token' => Str::random(60),
                    ])->save();

                    // Revoke all existing tokens for security
                    $user->tokens()->delete();

                    // Clear any pending 2FA codes
                    $this->twoFactorService->clearCode($user);

                    // Reset failed login attempts
                    $user->update([
                        'failed_login_attempts' => 0,
                        'locked_until' => null,
                    ]);

                    event(new PasswordReset($user));

                    Log::channel('auth')->info('Password reset successful', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'timestamp' => now()->toISOString(),
                    ]);
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'message' => 'Password has been reset successfully. Please log in with your new password.',
                ]);
            }

            Log::channel('auth')->warning('Password reset failed', [
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'status' => $status,
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Invalid or expired password reset token.',
            ], 400);

        } catch (\Exception $e) {
            Log::channel('auth')->error('Password reset process failed', [
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Password reset failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Verify email address with expiration handling and security checks.
     */
    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            // Verify the hash matches
            if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
                Log::channel('auth')->warning('Invalid email verification attempt - hash mismatch', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'provided_hash' => $hash,
                    'timestamp' => now()->toISOString(),
                ]);

                return response()->json([
                    'message' => 'Invalid or expired verification link.',
                ], 400);
            }

            // Check if email is already verified
            if ($user->hasVerifiedEmail()) {
                Log::channel('auth')->info('Email verification attempt on already verified account', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip(),
                    'timestamp' => now()->toISOString(),
                ]);

                return response()->json([
                    'message' => 'Email address is already verified.',
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'email_verified_at' => $user->email_verified_at->toISOString(),
                    ],
                ]);
            }

            // Mark email as verified
            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }

            Log::channel('auth')->info('Email verified successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Email verified successfully. You can now log in to your account.',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at->toISOString(),
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::channel('auth')->warning('Email verification attempt with invalid user ID', [
                'user_id' => $id,
                'ip' => $request->ip(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Invalid verification link.',
            ], 404);

        } catch (\Exception $e) {
            Log::channel('auth')->error('Email verification process failed', [
                'user_id' => $id,
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Email verification failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Resend email verification link.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => ['required', 'email:rfc', 'exists:users,email'],
            ]);

            $email = strtolower($request->input('email'));
            $user = User::where('email', $email)->first();

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'message' => 'Email address is already verified.',
                ]);
            }

            // Send verification email
            $user->sendEmailVerificationNotification();

            Log::channel('auth')->info('Email verification resent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Verification email sent. Please check your inbox.',
            ]);

        } catch (\Exception $e) {
            Log::channel('auth')->error('Resend verification failed', [
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Failed to send verification email. Please try again.',
            ], 500);
        }
    }

    /**
     * Get current authenticated user profile with comprehensive information.
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'avatar' => $user->avatar,
                'bio' => $user->bio,
                'location' => $user->location,
                'phone' => $user->phone,
                'is_admin' => $user->is_admin,
                'is_active' => $user->is_active,
                'average_rating' => $user->average_rating,
                'total_reviews' => $user->total_reviews,
                'jobs_completed' => $user->jobs_completed,
                'skills_offered' => $user->skills_offered,
                'last_login_at' => $user->last_login_at?->toISOString(),
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::channel('auth')->error('Failed to retrieve user profile', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Failed to retrieve user profile.',
            ], 500);
        }
    }

    /**
     * Prepare two-factor authentication setup (for future implementation).
     */
    public function prepareTwoFactor(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Generate backup recovery codes
            $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();

            Log::channel('auth')->info('Two-factor authentication preparation initiated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Two-factor authentication setup prepared.',
                'recovery_codes' => $recoveryCodes,
                'instructions' => [
                    'Save these recovery codes in a secure location.',
                    'Each code can only be used once.',
                    'You will need these codes if you lose access to your authentication device.',
                ],
            ]);

        } catch (\Exception $e) {
            Log::channel('auth')->error('Two-factor preparation failed', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Failed to prepare two-factor authentication.',
            ], 500);
        }
    }

    /**
     * Get active sessions for the current user.
     */
    public function activeSessions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentTokenId = $request->user()->currentAccessToken()->id;

            $sessions = $user->tokens()->get()->map(function ($token) use ($currentTokenId) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at?->toISOString(),
                    'created_at' => $token->created_at->toISOString(),
                    'expires_at' => $token->expires_at?->toISOString(),
                    'is_current' => $token->id === $currentTokenId,
                ];
            });

            return response()->json([
                'sessions' => $sessions,
                'total_sessions' => $sessions->count(),
            ]);

        } catch (\Exception $e) {
            Log::channel('auth')->error('Failed to retrieve active sessions', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Failed to retrieve active sessions.',
            ], 500);
        }
    }

    /**
     * Revoke a specific session token.
     */
    public function revokeSession(Request $request, string $tokenId): JsonResponse
    {
        try {
            $user = $request->user();
            $currentTokenId = $request->user()->currentAccessToken()->id;

            // Prevent revoking current session
            if ($tokenId === (string) $currentTokenId) {
                return response()->json([
                    'message' => 'Cannot revoke current session. Use logout instead.',
                ], 400);
            }

            $token = $user->tokens()->where('id', $tokenId)->first();

            if (! $token) {
                return response()->json([
                    'message' => 'Session not found.',
                ], 404);
            }

            $token->delete();

            Log::channel('auth')->info('Session revoked', [
                'user_id' => $user->id,
                'revoked_token_id' => $tokenId,
                'ip' => $request->ip(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Session revoked successfully.',
            ]);

        } catch (\Exception $e) {
            Log::channel('auth')->error('Failed to revoke session', [
                'user_id' => $request->user()?->id,
                'token_id' => $tokenId,
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'message' => 'Failed to revoke session.',
            ], 500);
        }
    }
}
