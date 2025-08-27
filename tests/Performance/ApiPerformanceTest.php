<?php

namespace Tests\Performance;

use App\Models\Category;
use App\Models\Job;
use App\Models\Message;
use App\Models\Review;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable query logging for performance tests
        DB::connection()->disableQueryLog();
    }

    public function test_job_listing_performance_with_large_dataset()
    {
        // Create large dataset
        $categories = Category::factory()->count(10)->create();
        $users = User::factory()->count(100)->create();

        // Create 1000 jobs
        Job::factory()->count(1000)->create([
            'category_id' => fn () => $categories->random()->id,
            'user_id' => fn () => $users->random()->id,
        ]);

        $startTime = microtime(true);
        $queryCount = 0;

        DB::listen(function ($query) use (&$queryCount) {
            $queryCount++;
        });

        $response = $this->getJson('/api/jobs?per_page=20');

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $response->assertStatus(200);
        $this->assertCount(20, $response->json('data'));

        // Performance assertions
        $this->assertLessThan(500, $executionTime, 'Job listing should complete within 500ms');
        $this->assertLessThan(10, $queryCount, 'Job listing should use fewer than 10 queries');
    }

    public function test_job_search_performance()
    {
        $category = Category::factory()->create();
        $users = User::factory()->count(50)->create();

        // Create jobs with searchable content
        Job::factory()->count(500)->create([
            'category_id' => $category->id,
            'user_id' => fn () => $users->random()->id,
            'title' => fn () => fake()->randomElement([
                'WordPress Development',
                'React Application',
                'Laravel Backend',
                'Vue.js Frontend',
                'Mobile App Development',
            ]),
        ]);

        $startTime = microtime(true);

        $response = $this->getJson('/api/jobs?search=WordPress&per_page=20');

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);

        // Performance assertions
        $this->assertLessThan(800, $executionTime, 'Job search should complete within 800ms');

        // Verify search results contain the search term
        $jobs = $response->json('data');
        foreach ($jobs as $job) {
            $this->assertStringContainsStringIgnoringCase('WordPress', $job['title'] . ' ' . $job['description']);
        }
    }

    public function test_user_profile_loading_performance()
    {
        $user = User::factory()->create();

        // Create related data
        Job::factory()->count(20)->create(['user_id' => $user->id]);
        Skill::factory()->count(10)->create(['user_id' => $user->id]);

        $reviewers = User::factory()->count(15)->create();
        foreach ($reviewers as $reviewer) {
            Review::factory()->create([
                'reviewer_id' => $reviewer->id,
                'reviewee_id' => $user->id,
                'job_id' => Job::factory()->create()->id,
            ]);
        }

        $token = $user->createToken('test-token')->plainTextToken;

        $startTime = microtime(true);
        $queryCount = 0;

        DB::listen(function ($query) use (&$queryCount) {
            $queryCount++;
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/users/profile');

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);

        // Performance assertions
        $this->assertLessThan(300, $executionTime, 'User profile should load within 300ms');
        $this->assertLessThan(8, $queryCount, 'User profile should use fewer than 8 queries');
    }

    public function test_message_conversation_loading_performance()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $token = $user1->createToken('test-token')->plainTextToken;

        // Create a conversation with many messages
        Message::factory()->count(200)->create([
            'sender_id' => fn () => fake()->randomElement([$user1->id, $user2->id]),
            'recipient_id' => fn ($attributes) => $attributes['sender_id'] === $user1->id ? $user2->id : $user1->id,
        ]);

        $startTime = microtime(true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/messages/conversation/{$user2->id}?per_page=50");

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertCount(50, $response->json('data'));

        // Performance assertions
        $this->assertLessThan(400, $executionTime, 'Message conversation should load within 400ms');
    }

    public function test_concurrent_job_creation_performance()
    {
        $users = User::factory()->count(10)->create();
        $category = Category::factory()->create();

        $tokens = [];
        foreach ($users as $user) {
            $tokens[] = $user->createToken('test-token')->plainTextToken;
        }

        $startTime = microtime(true);
        $responses = [];

        // Simulate concurrent job creation
        foreach ($tokens as $index => $token) {
            $responses[] = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->postJson('/api/jobs', [
                'title' => "Test Job {$index}",
                'description' => 'Test job description',
                'category_id' => $category->id,
                'budget_type' => 'fixed',
                'budget_min' => 100.00,
                'budget_max' => 500.00,
            ]);
        }

        $endTime = microtime(true);
        $totalExecutionTime = ($endTime - $startTime) * 1000;

        // Verify all jobs were created successfully
        foreach ($responses as $response) {
            $response->assertStatus(201);
        }

        // Performance assertions
        $this->assertLessThan(2000, $totalExecutionTime, 'Concurrent job creation should complete within 2 seconds');
        $this->assertSame(10, Job::count(), 'All 10 jobs should be created');
    }

    public function test_database_query_optimization()
    {
        $category = Category::factory()->create();
        $users = User::factory()->count(20)->create();

        Job::factory()->count(100)->create([
            'category_id' => $category->id,
            'user_id' => fn () => $users->random()->id,
        ]);

        $queryCount = 0;
        DB::listen(function ($query) use (&$queryCount) {
            $queryCount++;
        });

        // Test N+1 query prevention
        $response = $this->getJson('/api/jobs?per_page=50');

        $response->assertStatus(200);
        $this->assertCount(50, $response->json('data'));

        // Should use eager loading to prevent N+1 queries
        $this->assertLessThan(5, $queryCount, 'Should use eager loading to minimize queries');

        // Verify that user and category data is included
        $job = $response->json('data.0');
        $this->assertArrayHasKey('user', $job);
        $this->assertArrayHasKey('category', $job);
    }

    public function test_api_response_caching_performance()
    {
        $category = Category::factory()->create();
        Job::factory()->count(50)->create(['category_id' => $category->id]);

        // First request (cache miss)
        $startTime1 = microtime(true);
        $response1 = $this->getJson('/api/jobs');
        $endTime1 = microtime(true);
        $executionTime1 = ($endTime1 - $startTime1) * 1000;

        // Second request (cache hit)
        $startTime2 = microtime(true);
        $response2 = $this->getJson('/api/jobs');
        $endTime2 = microtime(true);
        $executionTime2 = ($endTime2 - $startTime2) * 1000;

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Second request should be significantly faster due to caching
        $this->assertLessThan($executionTime1 * 0.5, $executionTime2, 'Cached response should be at least 50% faster');
    }

    public function test_pagination_performance_with_large_offset()
    {
        $category = Category::factory()->create();
        $users = User::factory()->count(50)->create();

        Job::factory()->count(2000)->create([
            'category_id' => $category->id,
            'user_id' => fn () => $users->random()->id,
        ]);

        // Test performance with large offset (page 50)
        $startTime = microtime(true);

        $response = $this->getJson('/api/jobs?page=50&per_page=20');

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertCount(20, $response->json('data'));

        // Even with large offset, should complete reasonably quickly
        $this->assertLessThan(1000, $executionTime, 'Large offset pagination should complete within 1 second');
    }

    public function test_memory_usage_during_bulk_operations()
    {
        $initialMemory = memory_get_usage(true);

        $category = Category::factory()->create();
        $users = User::factory()->count(100)->create();

        // Create jobs in chunks to test memory efficiency
        for ($i = 0; $i < 10; $i++) {
            Job::factory()->count(100)->create([
                'category_id' => $category->id,
                'user_id' => fn () => $users->random()->id,
            ]);

            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable (less than 50MB)
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease, 'Memory usage should not exceed 50MB for bulk operations');

        $this->assertSame(1000, Job::count(), 'All 1000 jobs should be created');
    }

    public function test_api_rate_limiting_performance()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $successfulRequests = 0;
        $rateLimitedRequests = 0;

        $startTime = microtime(true);

        // Make rapid requests to test rate limiting
        for ($i = 0; $i < 100; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->getJson('/api/users/profile');

            if ($response->status() === 200) {
                $successfulRequests++;
            } elseif ($response->status() === 429) {
                $rateLimitedRequests++;
            }
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;

        // Rate limiting should kick in
        $this->assertGreaterThan(0, $rateLimitedRequests, 'Rate limiting should prevent some requests');
        $this->assertGreaterThan(0, $successfulRequests, 'Some requests should succeed');

        // Total time should be reasonable even with rate limiting
        $this->assertLessThan(10000, $totalTime, 'Rate limiting should not cause excessive delays');
    }
}
