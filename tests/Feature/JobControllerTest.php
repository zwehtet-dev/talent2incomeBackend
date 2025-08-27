<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->otherUser = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->category = Category::factory()->create();
    }

    public function test_can_list_jobs(): void
    {
        Sanctum::actingAs($this->user);

        Job::factory()->count(3)->create([
            'category_id' => $this->category->id,
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/jobs');

        $response->assertOk()
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
                        'is_urgent',
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
                'links',
            ]);
    }

    public function test_can_search_jobs_by_title(): void
    {
        Sanctum::actingAs($this->user);

        Job::factory()->create([
            'title' => 'WordPress Development',
            'category_id' => $this->category->id,
        ]);

        Job::factory()->create([
            'title' => 'React Development',
            'category_id' => $this->category->id,
        ]);

        $response = $this->getJson('/api/jobs?search=WordPress');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsString('WordPress', $response->json('data.0.title'));
    }

    public function test_can_filter_jobs_by_category(): void
    {
        Sanctum::actingAs($this->user);

        $webCategory = Category::factory()->create(['name' => 'Web Development']);
        $designCategory = Category::factory()->create(['name' => 'Design']);

        Job::factory()->create(['category_id' => $webCategory->id]);
        Job::factory()->create(['category_id' => $designCategory->id]);

        $response = $this->getJson("/api/jobs?category_id={$webCategory->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($webCategory->id, $response->json('data.0.category.id'));
    }

    public function test_can_filter_jobs_by_budget_range(): void
    {
        Sanctum::actingAs($this->user);

        Job::factory()->create([
            'budget_min' => 100,
            'budget_max' => 200,
            'category_id' => $this->category->id,
        ]);

        Job::factory()->create([
            'budget_min' => 500,
            'budget_max' => 1000,
            'category_id' => $this->category->id,
        ]);

        // Filter for jobs with budget range that overlaps with 250-400
        // This should only match the second job (500-1000)
        $response = $this->getJson('/api/jobs?budget_min=250&budget_max=400');

        $response->assertOk();
        $this->assertCount(0, $response->json('data')); // No jobs should match this range

        // Filter for jobs with budget range that overlaps with 150-600
        // This should match the second job (500-1000) since 500 <= 600
        $response = $this->getJson('/api/jobs?budget_min=150&budget_max=600');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_create_job(): void
    {
        Sanctum::actingAs($this->user);

        $jobData = [
            'title' => 'Build a WordPress Website',
            'description' => 'Need a professional WordPress website for my business.',
            'category_id' => $this->category->id,
            'budget_min' => 500,
            'budget_max' => 1000,
            'budget_type' => 'fixed',
            'deadline' => now()->addDays(30)->format('Y-m-d'),
            'is_urgent' => false,
        ];

        $response = $this->postJson('/api/jobs', $jobData);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'user',
                    'category',
                ],
            ]);

        $this->assertDatabaseHas('job_postings', [
            'title' => 'Build a WordPress Website',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_cannot_create_job_without_required_fields(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/jobs', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'description', 'category_id', 'budget_type']);
    }

    public function test_cannot_create_job_with_invalid_budget(): void
    {
        Sanctum::actingAs($this->user);

        $jobData = [
            'title' => 'Test Job',
            'description' => 'Test description',
            'category_id' => $this->category->id,
            'budget_min' => 1000,
            'budget_max' => 500, // Max less than min
            'budget_type' => 'fixed',
        ];

        $response = $this->postJson('/api/jobs', $jobData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['budget_max']);
    }

    public function test_can_view_job_details(): void
    {
        Sanctum::actingAs($this->user);

        $job = Job::factory()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->getJson("/api/jobs/{$job->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'budget_display',
                    'average_budget',
                    'is_expired',
                    'days_until_deadline',
                    'is_near_deadline',
                    'user',
                    'category',
                    'reviews',
                ],
            ]);
    }

    public function test_job_owner_can_update_job(): void
    {
        Sanctum::actingAs($this->user);

        $job = Job::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'status' => 'open',
        ]);

        $updateData = [
            'title' => 'Updated Job Title',
            'is_urgent' => true,
        ];

        $response = $this->putJson("/api/jobs/{$job->id}", $updateData);

        $response->assertOk()
            ->assertJson([
                'message' => 'Job updated successfully.',
                'data' => [
                    'title' => 'Updated Job Title',
                    'is_urgent' => true,
                ],
            ]);
    }

    public function test_cannot_update_others_job(): void
    {
        Sanctum::actingAs($this->user);

        $job = Job::factory()->create([
            'user_id' => $this->otherUser->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->putJson("/api/jobs/{$job->id}", [
            'title' => 'Hacked Title',
        ]);

        $response->assertForbidden();
    }

    public function test_can_update_job_status(): void
    {
        Sanctum::actingAs($this->user);

        $job = Job::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'status' => 'open',
            'assigned_to' => $this->otherUser->id,
        ]);

        $response = $this->putJson("/api/jobs/{$job->id}", [
            'status' => 'in_progress',
        ]);

        $response->assertOk();
        $this->assertSame('in_progress', $job->fresh()->status);
    }

    public function test_cannot_make_invalid_status_transition(): void
    {
        Sanctum::actingAs($this->user);

        $job = Job::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'status' => 'completed',
        ]);

        $response = $this->putJson("/api/jobs/{$job->id}", [
            'status' => 'open',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_job_owner_can_delete_job(): void
    {
        Sanctum::actingAs($this->user);

        $job = Job::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'status' => 'open',
        ]);

        $response = $this->deleteJson("/api/jobs/{$job->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Job deleted successfully.',
            ]);

        $this->assertSoftDeleted('job_postings', ['id' => $job->id]);
    }

    public function test_cannot_delete_job_in_progress(): void
    {
        Sanctum::actingAs($this->user);

        $job = Job::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'status' => 'in_progress',
        ]);

        $response = $this->deleteJson("/api/jobs/{$job->id}");

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Cannot delete job that is in progress.',
            ]);
    }

    public function test_can_get_my_jobs(): void
    {
        Sanctum::actingAs($this->user);

        Job::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        Job::factory()->create([
            'user_id' => $this->otherUser->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->getJson('/api/jobs/my-jobs');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));

        foreach ($response->json('data') as $job) {
            $this->assertSame($this->user->id, $job['user']['id']);
        }
    }

    public function test_can_get_assigned_jobs(): void
    {
        Sanctum::actingAs($this->user);

        Job::factory()->count(2)->create([
            'assigned_to' => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        Job::factory()->create([
            'assigned_to' => $this->otherUser->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->getJson('/api/jobs/assigned');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_search_endpoint_works(): void
    {
        Sanctum::actingAs($this->user);

        Job::factory()->create([
            'title' => 'Laravel Development',
            'category_id' => $this->category->id,
        ]);

        $response = $this->getJson('/api/jobs/search?search=Laravel');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_pagination_works(): void
    {
        Sanctum::actingAs($this->user);

        Job::factory()->count(25)->create([
            'category_id' => $this->category->id,
        ]);

        $response = $this->getJson('/api/jobs?per_page=10');

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertSame(25, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.last_page'));
    }

    public function test_sorting_by_deadline_works(): void
    {
        Sanctum::actingAs($this->user);

        $job1 = Job::factory()->create([
            'deadline' => now()->addDays(5),
            'category_id' => $this->category->id,
        ]);

        $job2 = Job::factory()->create([
            'deadline' => now()->addDays(2),
            'category_id' => $this->category->id,
        ]);

        $response = $this->getJson('/api/jobs?sort_by=deadline&sort_direction=asc');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame($job2->id, $data[0]['id']);
        $this->assertSame($job1->id, $data[1]['id']);
    }

    public function test_unauthenticated_user_cannot_access_jobs(): void
    {
        $response = $this->getJson('/api/jobs');

        $response->assertUnauthorized();
    }

    public function test_unverified_user_cannot_create_job(): void
    {
        $unverifiedUser = User::factory()->create([
            'email_verified_at' => null,
        ]);

        Sanctum::actingAs($unverifiedUser);

        $response = $this->postJson('/api/jobs', [
            'title' => 'Test Job',
            'description' => 'Test description',
            'category_id' => $this->category->id,
            'budget_min' => 100,
            'budget_type' => 'fixed',
        ]);

        $response->assertForbidden();
    }
}
