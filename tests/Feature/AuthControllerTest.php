<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Mail::fake();
    }

    /** @test */
    public function user_can_register_with_valid_data()
    {
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'UniqueTestPass123!',
            'password_confirmation' => 'UniqueTestPass123!',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'email_verified_at',
                ],
                'requires_verification',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // Event is dispatched but we're not testing it here since we're focusing on the API response
    }

    /** @test */
    public function registration_fails_with_invalid_data()
    {
        $userData = [
            'first_name' => '',
            'last_name' => 'Doe',
            'email' => 'invalid-email',
            'password' => 'weak',
            'password_confirmation' => 'different',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'email', 'password']);
    }

    /** @test */
    public function registration_fails_with_duplicate_email()
    {
        User::factory()->create(['email' => 'john@example.com']);

        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'email_verified_at',
                    'is_admin',
                ],
                'token',
                'token_type',
                'expires_at',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'failed_login_attempts' => 0,
        ]);
    }

    /** @test */
    public function login_fails_with_invalid_credentials()
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    /** @test */
    public function login_fails_with_unverified_email()
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Please verify your email address before logging in.',
                'requires_verification' => true,
            ]);
    }

    /** @test */
    public function login_fails_with_locked_account()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'email_verified_at' => now(),
            'locked_until' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(423)
            ->assertJsonStructure([
                'message',
                'locked_until',
                'time_remaining_seconds',
            ]);
    }

    /** @test */
    public function login_fails_with_inactive_account()
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'email_verified_at' => now(),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Account is inactive. Please contact support.']);
    }

    /** @test */
    public function failed_login_attempts_are_tracked()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'email_verified_at' => now(),
        ]);

        // Make multiple failed login attempts
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'john@example.com',
                'password' => 'WrongPassword',
            ]);
        }

        $user->refresh();
        $this->assertSame(3, $user->failed_login_attempts);
    }

    /** @test */
    public function user_can_logout()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);

        // Token should be revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    /** @test */
    public function user_can_logout_from_all_devices()
    {
        $user = User::factory()->create();
        $token1 = $user->createToken('token1')->plainTextToken;
        $token2 = $user->createToken('token2')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->postJson('/api/auth/logout-all');

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'tokens_revoked']);

        // All tokens should be revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    /** @test */
    public function user_can_request_password_reset()
    {
        $user = User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'If an account with that email exists, we have sent a password reset link.',
            ]);
    }

    /** @test */
    public function password_reset_request_with_nonexistent_email_returns_success()
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'If an account with that email exists, we have sent a password reset link.',
            ]);
    }

    /** @test */
    public function user_can_verify_email()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $hash = sha1($user->getEmailForVerification());

        $response = $this->getJson("/api/auth/verify-email/{$user->id}/{$hash}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Email verified successfully. You can now log in to your account.']);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        // Event is dispatched but we're not testing it here since we're focusing on the API response
    }

    /** @test */
    public function email_verification_fails_with_invalid_hash()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this->getJson("/api/auth/verify-email/{$user->id}/invalid-hash");

        $response->assertStatus(400)
            ->assertJson(['message' => 'Invalid or expired verification link.']);

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    /** @test */
    public function user_can_resend_verification_email()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/auth/resend-verification', [
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Verification email sent. Please check your inbox.']);
    }

    /** @test */
    public function authenticated_user_can_get_profile()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'first_name',
                'last_name',
                'full_name',
                'email',
                'email_verified_at',
                'avatar',
                'bio',
                'location',
                'phone',
                'is_admin',
                'is_active',
                'average_rating',
                'total_reviews',
                'jobs_completed',
                'skills_offered',
                'last_login_at',
                'created_at',
                'updated_at',
            ]);
    }

    /** @test */
    public function user_can_get_active_sessions()
    {
        $user = User::factory()->create();
        $token1 = $user->createToken('token1')->plainTextToken;
        $token2 = $user->createToken('token2')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->getJson('/api/auth/sessions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'sessions' => [
                    '*' => [
                        'id',
                        'name',
                        'abilities',
                        'last_used_at',
                        'created_at',
                        'expires_at',
                        'is_current',
                    ],
                ],
                'total_sessions',
            ]);
    }

    /** @test */
    public function user_can_revoke_specific_session()
    {
        $user = User::factory()->create();
        $token1 = $user->createToken('token1')->plainTextToken;
        $token2 = $user->createToken('token2');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->deleteJson("/api/auth/sessions/{$token2->accessToken->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Session revoked successfully.']);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token2->accessToken->id,
        ]);
    }

    /** @test */
    public function user_cannot_revoke_current_session()
    {
        $user = User::factory()->create();
        $token = $user->createToken('token1');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken,
        ])->deleteJson("/api/auth/sessions/{$token->accessToken->id}");

        $response->assertStatus(400)
            ->assertJson(['message' => 'Cannot revoke current session. Use logout instead.']);
    }

    /** @test */
    public function user_can_prepare_two_factor_authentication()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/2fa/prepare');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'recovery_codes',
                'instructions',
            ]);
    }
}
