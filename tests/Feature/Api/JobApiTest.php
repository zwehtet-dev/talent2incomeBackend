<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_job_with_valid_data()
    {
        [$user, $token] = $this->authenticatedUser();
        $category = Category::factory()->create();

        $jobData = [
            'title' => 'Build a WordPress Website',
            'description' => 'Need a professional WordPress website for my business',
            'category_id' => $category->id,
            'budget_min' => 500.00,
            'budget_max' => 1000.00,
            'budget_type' => 'fixed',
            'deadline' => now()->addDays(30)->format('Y-m-d'),
            'is_urgent' => false,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/jobs', $jobData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'budget_min',
                'budget_max',
                'budget_type',
                'deadline',
                'status',
                'user' => ['id', 'first_name', 'last_name'],
                'category' => ['id', 'name'],
            ]);

        $this->assertDatabaseHas('jobs', [
            'title' => 'Build a WordPress Website',
            'user_id' => $user->id,
            'category_id' => $category->id,
            'status' => 'open',
        ]);
    }

    public function test_job_creation_fails_with_invalid_data()
    {
        [$user, $token] = $this->authenticatedUser();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/jobs', [
            'title' => '', // Required field
            'description' => '', // Required field
            'budget_min' => -100, // Invalid negative value
            'budget_type' => 'invalid_type', // Invalid enum value
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'description', 'category_id', 'budget_min', 'budget_type']);
    }

    public function test_unauthenticated_user_cannot_create_job()
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/jobs', [
            'title' => 'Test Job',
            'description' => 'Test description',
            'category_id' => $category->id,
            'budget_type' => 'fixed',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_view_job_list()
    {
        $category = Category::factory()->create();
        Job::factory()->count(5)->create(['category_id' => $category->id]);

        $response = $this->getJson('/api/jobs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'budget_min',
                        'budget_max',
                        'budget_type',
                        'deadline',
                        'status',
                        'user',
                        'category',
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

        $this->assertCount(5, $response->json('data'));
    }

    public function test_job_list_pagination_works()
    {
        $category = Category::factory()->create();
        Job::factory()->count(25)->create(['category_id' => $category->id]);

        $response = $this->getJson('/api/jobs?page=1&per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertSame(1, $response->json('meta.current_page'));
        $this->assertSame(25, $response->json('meta.total'));
    }

    public function test_user_can_view_specific_job()
    {
        $job = Job::factory()->create();

        $response = $this->getJson("/api/jobs/{$job->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'budget_min',
                'budget_max',
                'budget_type',
                'deadline',
                'status',
                'is_urgent',
                'user' => [
                    'id',
                    'first_name',
                    'last_name',
                    'avatar',
                    'average_rating',
                ],
                'category' => [
                    'id',
                    'name',
                    'slug',
                ],
                'created_at',
            ])
            ->assertJson([
                'id' => $job->id,
                'title' => $job->title,
            ]);
    }

    public function test_viewing_nonexistent_job_returns_404()
    {
        $response = $this->getJson('/api/jobs/999999');

        $response->assertStatus(404);
    }

    public function test_job_owner_can_update_job()
    {
        [$user, $token] = $this->authenticatedUser();
        $job = Job::factory()->create(['user_id' => $user->id]);

        $updateData = [
            'title' => 'Updated Job Title',
            'description' => 'Updated job description',
            'budget_max' => 1500.00,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/jobs/{$job->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'title' => 'Updated Job Title',
                'description' => 'Updated job description',
                'budget_max' => 1500.00,
            ]);

        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
            'title' => 'Updated Job Title',
        ]);
    }

    public function test_non_owner_cannot_update_job()
    {
        [$user, $token] = $this->authenticatedUser();
        $otherUser = User::factory()->create();
        $job = Job::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/jobs/{$job->id}", [
            'title' => 'Unauthorized Update',
        ]);

        $response->assertStatus(403);
    }

    public function test_job_owner_can_delete_job()
    {
        [$user, $token] = $this->authenticatedUser();
        $job = Job::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/jobs/{$job->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('jobs', ['id' => $job->id]);
    }

    public function test_non_owner_cannot_delete_job()
    {
        [$user, $token] = $this->authenticatedUser();
        $otherUser = User::factory()->create();
        $job = Job::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/jobs/{$job->id}");

        $response->assertStatus(403);
    }

    public function test_job_search_functionality()
    {
        $category = Category::factory()->create();
        Job::factory()->create([
            'title' => 'WordPress Development',
            'description' => 'Build a WordPress site',
            'category_id' => $category->id,
        ]);
        Job::factory()->create([
            'title' => 'React Application',
            'description' => 'Build a React app',
            'category_id' => $category->id,
        ]);

        $response = $this->getJson('/api/jobs?search=WordPress');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsString('WordPress', $response->json('data.0.title'));
    }

    public function test_job_filtering_by_category()
    {
        $webCategory = Category::factory()->create(['name' => 'Web Development']);
        $designCategory = Category::factory()->create(['name' => 'Graphic Design']);

        Job::factory()->count(3)->create(['category_id' => $webCategory->id]);
        Job::factory()->count(2)->create(['category_id' => $designCategory->id]);

        $response = $this->getJson("/api/jobs?category_id={$webCategory->id}");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_job_filtering_by_budget_range()
    {
        $category = Category::factory()->create();
        Job::factory()->create(['budget_min' => 100, 'budget_max' => 300, 'category_id' => $category->id]);
        Job::factory()->create(['budget_min' => 500, 'budget_max' => 800, 'category_id' => $category->id]);
        Job::factory()->create(['budget_min' => 1000, 'budget_max' => 1500, 'category_id' => $category->id]);

        $response = $this->getJson('/api/jobs?budget_min=400&budget_max=900');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_job_filtering_by_status()
    {
        $category = Category::factory()->create();
        Job::factory()->create(['status' => 'open', 'category_id' => $category->id]);
        Job::factory()->create(['status' => 'in_progress', 'category_id' => $category->id]);
        Job::factory()->create(['status' => 'completed', 'category_id' => $category->id]);

        $response = $this->getJson('/api/jobs?status=open');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('open', $response->json('data.0.status'));
    }

    public function test_urgent_jobs_filtering()
    {
        $category = Category::factory()->create();
        Job::factory()->create(['is_urgent' => true, 'category_id' => $category->id]);
        Job::factory()->create(['is_urgent' => false, 'category_id' => $category->id]);

        $response = $this->getJson('/api/jobs?urgent=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_urgent'));
    }

    public function test_job_assignment_functionality()
    {
        [$jobOwner, $ownerToken] = $this->authenticatedUser();
        [$assignee, $assigneeToken] = $this->authenticatedUser();

        $job = Job::factory()->create([
            'user_id' => $jobOwner->id,
            'status' => 'open',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $ownerToken,
        ])->postJson("/api/jobs/{$job->id}/assign", [
            'assigned_to' => $assignee->id,
        ]);

        $response->assertStatus(200);

        $job->refresh();
        $this->assertSame($assignee->id, $job->assigned_to);
        $this->assertSame('in_progress', $job->status);
    }

    public function test_job_completion_functionality()
    {
        [$user, $token] = $this->authenticatedUser();
        $job = Job::factory()->create([
            'user_id' => $user->id,
            'status' => 'in_progress',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/jobs/{$job->id}/complete");

        $response->assertStatus(200);

        $job->refresh();
        $this->assertSame('completed', $job->status);
    }

    public function test_job_deadline_validation()
    {
        [$user, $token] = $this->authenticatedUser();
        $category = Category::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/jobs', [
            'title' => 'Test Job',
            'description' => 'Test description',
            'category_id' => $category->id,
            'budget_type' => 'fixed',
            'deadline' => now()->subDays(1)->format('Y-m-d'), // Past date
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['deadline']);
    }

    public function test_job_budget_validation()
    {
        [$user, $token] = $this->authenticatedUser();
        $category = Category::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/jobs', [
            'title' => 'Test Job',
            'description' => 'Test description',
            'category_id' => $category->id,
            'budget_min' => 1000.00,
            'budget_max' => 500.00, // Max less than min
            'budget_type' => 'fixed',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['budget_max']);
    }

    public function test_job_list_ordering()
    {
        $category = Category::factory()->create();
        $oldJob = Job::factory()->create(['created_at' => now()->subDays(2), 'category_id' => $category->id]);
        $newJob = Job::factory()->create(['created_at' => now(), 'category_id' => $category->id]);

        $response = $this->getJson('/api/jobs?sort=created_at&order=desc');

        $response->assertStatus(200);
        $this->assertSame($newJob->id, $response->json('data.0.id'));
    }

    private function authenticatedUser()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        return [$user, $token];
    }
}
