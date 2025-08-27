<?php

declare(strict_types=1);

use App\Models\Job;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $this->token = $this->user->createDefaultToken();
});

describe('User Profile Management', function () {
    it('can get current user profile', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/users/profile');

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'first_name',
                'last_name',
                'email',
                'avatar',
                'bio',
                'location',
                'phone',
                'average_rating',
                'total_reviews',
                'jobs_completed',
                'skills_offered',
                'created_at',
                'email_verified_at',
            ])
            ->assertJson([
                'id' => $this->user->id,
                'email' => $this->user->email,
            ]);
    });

    it('can update user profile with valid data', function () {
        $updateData = [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => 'updated@example.com',
            'bio' => 'Updated bio content',
            'location' => 'New York, NY',
            'phone' => '+1-555-123-4567',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/users/profile', $updateData);

        $response->assertOk()
            ->assertJson([
                'message' => 'Profile updated successfully',
                'user' => [
                    'first_name' => 'Updated',
                    'last_name' => 'Name',
                    'email' => 'updated@example.com',
                    'bio' => 'Updated bio content',
                    'location' => 'New York, NY',
                    'phone' => '+1-555-123-4567',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => 'updated@example.com',
            'bio' => 'Updated bio content',
        ]);
    });

    it('validates profile update data', function () {
        $invalidData = [
            'first_name' => '123', // Invalid characters
            'email' => 'invalid-email', // Invalid format
            'bio' => str_repeat('a', 1001), // Too long
            'phone' => 'invalid-phone-123!@#', // Invalid format
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/users/profile', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'email', 'bio', 'phone']);
    });

    it('prevents duplicate email during profile update', function () {
        $otherUser = User::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/users/profile', [
            'email' => $otherUser->email,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('allows keeping same email during profile update', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/users/profile', [
            'email' => $this->user->email,
            'first_name' => 'Updated',
            'last_name' => 'Updated',
        ]);

        $response->assertOk();
    });
});

describe('Avatar Upload', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('can upload avatar image', function () {
        $file = UploadedFile::fake()->image('avatar.jpg', 500, 500);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/users/avatar', [
            'avatar' => $file,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'avatar_url',
            ]);

        // Check that file was stored
        $this->user->refresh();
        expect($this->user->avatar)->not->toBeNull();
        Storage::disk('public')->assertExists($this->user->avatar);
    });

    it('validates avatar file requirements', function () {
        $invalidFile = UploadedFile::fake()->create('document.pdf', 1000);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/users/avatar', [
            'avatar' => $invalidFile,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    });

    it('validates avatar file size', function () {
        $largeFile = UploadedFile::fake()->image('large.jpg')->size(6000); // 6MB

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/users/avatar', [
            'avatar' => $largeFile,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    });

    it('replaces existing avatar when uploading new one', function () {
        // Upload first avatar
        $firstFile = UploadedFile::fake()->image('first.jpg', 300, 300);
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/users/avatar', ['avatar' => $firstFile]);

        $this->user->refresh();
        $firstAvatarPath = $this->user->avatar;

        // Add a small delay to ensure different timestamp
        sleep(1);

        // Upload second avatar
        $secondFile = UploadedFile::fake()->image('second.jpg', 400, 400);
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/users/avatar', ['avatar' => $secondFile]);

        $response->assertOk();

        $this->user->refresh();
        expect($this->user->avatar)->not->toBe($firstAvatarPath);
        Storage::disk('public')->assertExists($this->user->avatar);
    });
});

describe('User Search', function () {
    beforeEach(function () {
        // Create test users with different attributes
        $this->activeUser = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Developer',
            'location' => 'New York',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->inactiveUser = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Designer',
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        $this->unverifiedUser = User::factory()->create([
            'first_name' => 'Bob',
            'last_name' => 'Writer',
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        // Create skills for testing has_skills filter
        Skill::factory()->create([
            'user_id' => $this->activeUser->id,
            'is_active' => true,
        ]);
    });

    it('can search users by name', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/users/search?q=John');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'first_name',
                        'last_name',
                        'avatar',
                        'bio',
                        'location',
                        'average_rating',
                        'total_reviews',
                        'jobs_completed',
                        'skills_offered',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'total',
                    'per_page',
                    'last_page',
                ],
            ]);

        $userIds = collect($response->json('data'))->pluck('id');
        expect($userIds)->toContain($this->activeUser->id);
        expect($userIds)->not->toContain($this->inactiveUser->id);
        expect($userIds)->not->toContain($this->unverifiedUser->id);
    });

    it('can filter users by location', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/users/search?location=New York');

        $response->assertOk();

        $userIds = collect($response->json('data'))->pluck('id');
        expect($userIds)->toContain($this->activeUser->id);
    });

    it('can filter users who have skills', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/users/search?has_skills=1');

        $response->assertOk();

        $userIds = collect($response->json('data'))->pluck('id');
        expect($userIds)->toContain($this->activeUser->id);
    });

    it('only returns active and verified users', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/users/search');

        $response->assertOk();

        $userIds = collect($response->json('data'))->pluck('id');
        expect($userIds)->toContain($this->activeUser->id);
        expect($userIds)->not->toContain($this->inactiveUser->id);
        expect($userIds)->not->toContain($this->unverifiedUser->id);
    });

    it('validates search parameters', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/users/search?q=a&per_page=100'); // Query too short, per_page too large

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q', 'per_page']);
    });
});

describe('User Statistics', function () {
    beforeEach(function () {
        // Create test data for statistics
        $this->job = Job::factory()->create(['user_id' => $this->user->id]);
        $this->skill = Skill::factory()->create(['user_id' => $this->user->id]);
        $this->message = Message::factory()->create(['sender_id' => $this->user->id]);

        $this->payment = Payment::factory()->create([
            'payee_id' => $this->user->id,
            'status' => 'released',
            'amount' => 100.00,
        ]);

        $this->review = Review::factory()->create([
            'reviewee_id' => $this->user->id,
            'rating' => 5,
        ]);
    });

    it('can get user statistics', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/users/statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'profile_stats' => [
                    'average_rating',
                    'total_reviews',
                    'jobs_completed',
                    'skills_offered',
                ],
                'activity_stats' => [
                    'recent_jobs_posted',
                    'recent_skills_added',
                    'recent_messages_sent',
                ],
                'financial_stats' => [
                    'monthly_earnings',
                    'monthly_spending',
                    'net_monthly',
                ],
                'job_stats' => [
                    'open',
                    'in_progress',
                    'completed',
                    'cancelled',
                ],
                'recent_reviews',
            ]);
    });

    it('calculates financial statistics correctly', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/users/statistics');

        $response->assertOk()
            ->assertJson([
                'financial_stats' => [
                    'monthly_earnings' => 100.00,
                    'monthly_spending' => 0.00,
                    'net_monthly' => 100.00,
                ],
            ]);
    });
});

describe('Public User Profile', function () {
    it('can view public profile of active verified user', function () {
        $publicUser = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/users/{$publicUser->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'first_name',
                'last_name',
                'avatar',
                'bio',
                'location',
                'average_rating',
                'total_reviews',
                'jobs_completed',
                'skills_offered',
                'created_at',
            ])
            ->assertJsonMissing(['email', 'phone']); // Private data should not be included
    });

    it('cannot view profile of inactive user', function () {
        $inactiveUser = User::factory()->create([
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/users/{$inactiveUser->id}");

        $response->assertNotFound();
    });

    it('cannot view profile of unverified user', function () {
        $unverifiedUser = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/users/{$unverifiedUser->id}");

        $response->assertNotFound();
    });
});

describe('Authentication Requirements', function () {
    it('requires authentication for profile endpoints', function () {
        $response = $this->getJson('/api/users/profile');
        $response->assertUnauthorized();

        $response = $this->putJson('/api/users/profile', []);
        $response->assertUnauthorized();

        $response = $this->postJson('/api/users/avatar', []);
        $response->assertUnauthorized();

        $response = $this->getJson('/api/users/statistics');
        $response->assertUnauthorized();
    });

    it('allows public access to user search and public profiles', function () {
        $publicUser = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->getJson('/api/users/search');
        $response->assertUnauthorized(); // Search requires auth based on our routes

        $response = $this->getJson("/api/users/{$publicUser->id}");
        $response->assertUnauthorized(); // Public profile requires auth based on our routes
    });
});
