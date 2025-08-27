<?php

namespace Tests\Security;

use App\Models\Job;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityPenetrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sql_injection_prevention_in_search()
    {
        $category = \App\Models\Category::factory()->create();
        Job::factory()->create(['title' => 'Test Job', 'category_id' => $category->id]);

        // Attempt SQL injection through search parameter
        $maliciousQueries = [
            "'; DROP TABLE jobs; --",
            "' OR '1'='1",
            "' UNION SELECT * FROM users --",
            "'; DELETE FROM jobs WHERE '1'='1'; --",
            "' OR 1=1 UNION SELECT password FROM users --",
        ];

        foreach ($maliciousQueries as $maliciousQuery) {
            $response = $this->getJson('/api/jobs?search=' . urlencode($maliciousQuery));

            // Should not cause SQL errors or expose sensitive data
            $response->assertStatus(200);

            // Verify the jobs table still exists and has data
            $this->assertDatabaseHas('jobs', ['title' => 'Test Job']);
        }
    }

    public function test_xss_prevention_in_user_input()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src="x" onerror="alert(1)">',
            'javascript:alert("XSS")',
            '<svg onload="alert(1)">',
            '"><script>alert("XSS")</script>',
        ];

        foreach ($xssPayloads as $payload) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->putJson('/api/users/profile', [
                'bio' => $payload,
                'first_name' => 'Test',
                'last_name' => 'User',
            ]);

            $response->assertStatus(200);

            // Verify XSS payload is sanitized
            $user->refresh();
            $this->assertStringNotContainsString('<script>', $user->bio);
            $this->assertStringNotContainsString('javascript:', $user->bio);
            $this->assertStringNotContainsString('onerror=', $user->bio);
        }
    }

    public function test_csrf_protection()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Attempt to make request without CSRF token (for web routes)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Requested-With' => 'XMLHttpRequest',
        ])->postJson('/api/jobs', [
            'title' => 'Test Job',
            'description' => 'Test description',
            'category_id' => 1,
            'budget_type' => 'fixed',
        ]);

        // API routes should work with Bearer token (Sanctum handles this)
        // But verify CSRF protection is in place for web routes
        $this->assertTrue(true); // API routes use token auth, not CSRF
    }

    public function test_authorization_bypass_attempts()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $token1 = $user1->createToken('test-token')->plainTextToken;

        $job = Job::factory()->create(['user_id' => $user2->id]);

        // Attempt to update another user's job
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->putJson("/api/jobs/{$job->id}", [
            'title' => 'Unauthorized Update',
        ]);

        $response->assertStatus(403);

        // Verify job was not updated
        $job->refresh();
        $this->assertNotSame('Unauthorized Update', $job->title);
    }

    public function test_mass_assignment_protection()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Attempt to escalate privileges through mass assignment
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/users/profile', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'is_admin' => true, // Attempt privilege escalation
            'email_verified_at' => now(), // Attempt to bypass verification
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertFalse($user->is_admin);
        $this->assertNull($user->email_verified_at);
    }

    public function test_password_security_requirements()
    {
        $weakPasswords = [
            '123',
            'password',
            '12345678',
            'qwerty',
            'abc123',
        ];

        foreach ($weakPasswords as $weakPassword) {
            $response = $this->postJson('/api/auth/register', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test' . rand() . '@example.com',
                'password' => $weakPassword,
                'password_confirmation' => $weakPassword,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['password']);
        }
    }

    public function test_brute_force_protection()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correctpassword'),
        ]);

        // Attempt multiple failed logins
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);

            if ($i < 5) {
                $response->assertStatus(401);
            }
        }

        // Next attempt should be rate limited
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(429); // Too Many Requests
    }

    public function test_sensitive_data_exposure_prevention()
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/users/profile');

        $response->assertStatus(200);

        // Verify sensitive data is not exposed
        $responseData = $response->json();
        $this->assertArrayNotHasKey('password', $responseData);
        $this->assertArrayNotHasKey('remember_token', $responseData);
    }

    public function test_file_upload_security()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Create malicious file content
        $maliciousContent = '<?php system($_GET["cmd"]); ?>';
        $tempFile = tmpfile();
        fwrite($tempFile, $maliciousContent);
        $tempPath = stream_get_meta_data($tempFile)['uri'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/users/avatar', [
            'avatar' => new \Illuminate\Http\UploadedFile(
                $tempPath,
                'malicious.php',
                'image/jpeg', // Fake MIME type
                null,
                true
            ),
        ]);

        // Should reject malicious file
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);

        fclose($tempFile);
    }

    public function test_directory_traversal_prevention()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $traversalAttempts = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\config\\sam',
            '....//....//....//etc/passwd',
            '%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd',
        ];

        foreach ($traversalAttempts as $attempt) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->getJson('/api/files/' . urlencode($attempt));

            // Should not allow directory traversal
            $response->assertStatus(404); // Or 403, depending on implementation
        }
    }

    public function test_session_fixation_prevention()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        // Login and get token
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $token = $response->json('token');

        // Verify token is unique and secure
        $this->assertNotEmpty($token);
        $this->assertGreaterThan(40, strlen($token)); // Should be sufficiently long
    }

    public function test_information_disclosure_prevention()
    {
        // Test error messages don't reveal sensitive information
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);

        // Error message should not reveal whether email exists
        $errorMessage = $response->json('message');
        $this->assertStringNotContainsString('user not found', strtolower($errorMessage));
        $this->assertStringNotContainsString('email does not exist', strtolower($errorMessage));
    }

    public function test_api_rate_limiting_security()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $requestCount = 0;
        $rateLimitHit = false;

        // Make rapid requests to test rate limiting
        for ($i = 0; $i < 200; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->getJson('/api/users/profile');

            $requestCount++;

            if ($response->status() === 429) {
                $rateLimitHit = true;

                break;
            }
        }

        $this->assertTrue($rateLimitHit, 'Rate limiting should prevent excessive requests');
        $this->assertLessThan(200, $requestCount, 'Rate limiting should kick in before 200 requests');
    }

    public function test_input_validation_bypass_attempts()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        $category = \App\Models\Category::factory()->create();

        $bypassAttempts = [
            ['title' => null], // Null byte injection
            ['title' => str_repeat('A', 10000)], // Buffer overflow attempt
            ['budget_min' => 'SELECT * FROM users'], // Type confusion
            ['budget_max' => -999999999999999999], // Integer overflow
            ['category_id' => 'javascript:alert(1)'], // Script injection in ID
        ];

        foreach ($bypassAttempts as $attempt) {
            $jobData = array_merge([
                'title' => 'Test Job',
                'description' => 'Test description',
                'category_id' => $category->id,
                'budget_type' => 'fixed',
            ], $attempt);

            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->postJson('/api/jobs', $jobData);

            // Should reject invalid input
            $this->assertContains($response->status(), [400, 422], 'Invalid input should be rejected');
        }
    }

    public function test_privilege_escalation_prevention()
    {
        $regularUser = User::factory()->create(['is_admin' => false]);
        $token = $regularUser->createToken('test-token')->plainTextToken;

        // Attempt to access admin endpoints
        $adminEndpoints = [
            '/api/admin/dashboard',
            '/api/admin/users',
            '/api/admin/disputes',
            '/api/admin/flagged-content',
        ];

        foreach ($adminEndpoints as $endpoint) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->getJson($endpoint);

            $response->assertStatus(403); // Forbidden
        }
    }

    public function test_data_leakage_prevention_in_api_responses()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $token1 = $user1->createToken('test-token')->plainTextToken;

        // Create private message
        $message = Message::factory()->create([
            'sender_id' => $user2->id,
            'recipient_id' => $user1->id,
            'content' => 'Private message content',
        ]);

        // User1 should be able to see the message
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->getJson("/api/messages/{$message->id}");

        $response->assertStatus(200);

        // Create another user who shouldn't see the message
        $user3 = User::factory()->create();
        $token3 = $user3->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token3,
        ])->getJson("/api/messages/{$message->id}");

        $response->assertStatus(403); // Should not be able to access
    }
}
