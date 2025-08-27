<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123', // Will be hashed by the mutator
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_uses_secure_password_hashing()
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();

        // Check if using bcrypt (fallback) or argon2id (preferred)
        $this->assertTrue(
            str_starts_with($user->password, '$2y$') || // bcrypt
            str_starts_with($user->password, '$argon2id$') // argon2id
        );

        // Verify password can be verified
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    /** @test */
    public function it_creates_tokens_with_custom_abilities()
    {
        $token = $this->user->createDefaultToken('test-token');

        $this->assertNotEmpty($token);

        // Verify token has expected abilities
        $tokenModel = $this->user->tokens()->first();
        $abilities = $tokenModel->abilities;

        $expectedAbilities = [
            'user:read', 'user:write',
            'jobs:read', 'jobs:write',
            'skills:read', 'skills:write',
            'messages:read', 'messages:write',
            'payments:read', 'payments:write',
            'reviews:read', 'reviews:write',
        ];

        foreach ($expectedAbilities as $ability) {
            $this->assertContains($ability, $abilities);
        }
    }

    /** @test */
    public function it_creates_admin_tokens_with_full_access()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createDefaultToken('admin-token');

        $tokenModel = $admin->tokens()->first();
        $this->assertContains('*', $tokenModel->abilities);
    }

    /** @test */
    public function it_enforces_rate_limiting_on_login_attempts()
    {
        // Make 5 failed login attempts (the limit)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);
            $response->assertStatus(401);
        }

        // 6th attempt should be rate limited
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
        $response->assertJsonStructure(['message', 'retry_after']);
    }

    /** @test */
    public function it_locks_account_after_failed_attempts()
    {
        // Make 5 failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->user->refresh();
        $this->assertTrue($this->user->isLocked());
        $this->assertSame(5, $this->user->failed_login_attempts);
        $this->assertNotNull($this->user->locked_until);
    }

    /** @test */
    public function it_prevents_login_on_locked_account()
    {
        // Lock the account
        $this->user->update([
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(423);
        $response->assertJsonStructure([
            'message',
            'locked_until',
            'time_remaining',
        ]);
    }

    /** @test */
    public function it_resets_failed_attempts_on_successful_login()
    {
        // Set some failed attempts
        $this->user->update(['failed_login_attempts' => 3]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertSame(0, $this->user->failed_login_attempts);
        $this->assertNull($this->user->locked_until);
        $this->assertNotNull($this->user->last_login_at);
    }

    /** @test */
    public function it_tracks_ip_addresses_and_login_history()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertNotNull($this->user->last_login_ip);
        $this->assertNotNull($this->user->login_history);
        $this->assertIsArray($this->user->login_history);
        $this->assertCount(1, $this->user->login_history);

        $loginRecord = $this->user->login_history[0];
        $this->assertArrayHasKey('ip', $loginRecord);
        $this->assertArrayHasKey('timestamp', $loginRecord);
        $this->assertArrayHasKey('user_agent', $loginRecord);
    }

    /** @test */
    public function it_prevents_access_to_inactive_accounts()
    {
        $this->user->update(['is_active' => false]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Account is inactive. Please contact support.']);
    }

    /** @test */
    public function it_includes_token_expiration_in_login_response()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'user',
            'token',
            'expires_at',
        ]);

        $expiresAt = $response->json('expires_at');
        $this->assertNotNull($expiresAt);
    }

    /** @test */
    public function it_revokes_token_on_logout()
    {
        // Login to get a token
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $response->json('token');

        // Use token to logout
        $response = $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);

        // Verify token is revoked
        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_blocks_access_to_locked_accounts_via_middleware()
    {
        // Create a token for the user
        Sanctum::actingAs($this->user);

        // Lock the account
        $this->user->update([
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(423);
        $response->assertJsonStructure([
            'message',
            'locked_until',
            'time_remaining',
        ]);
    }

    /** @test */
    public function it_blocks_access_to_inactive_accounts_via_middleware()
    {
        Sanctum::actingAs($this->user);

        $this->user->update(['is_active' => false]);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Account is inactive. Please contact support.']);
    }

    /** @test */
    public function it_applies_rate_limiting_to_registration()
    {
        // Make 5 registration attempts (the limit)
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/register', [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => "test{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        }

        // 6th attempt should be rate limited
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'test6@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(429);
    }

    /** @test */
    public function it_includes_rate_limit_headers()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
        $response->assertHeader('X-RateLimit-Reset');
    }

    /** @test */
    public function it_validates_token_abilities()
    {
        $token = $this->user->createTokenWithAbilities('limited-token', ['user:read']);

        Sanctum::actingAs($this->user, ['user:read']);

        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(200);

        // This would fail if we tried to access an endpoint requiring 'user:write'
        // but since /api/auth/me only requires 'user:read', it should work
    }
}
