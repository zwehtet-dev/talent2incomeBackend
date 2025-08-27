<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SkillControllerTest extends TestCase
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

    public function test_can_list_skills(): void
    {
        Skill::factory()->count(3)->create([
            'category_id' => $this->category->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/skills');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'pricing_type',
                        'is_available',
                        'user' => ['id', 'first_name', 'last_name'],
                        'category' => ['id', 'name', 'slug'],
                    ],
                ],
                'meta' => [
                    'current_page',
                    'total',
                    'per_page',
                    'last_page',
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_filter_skills_by_category(): void
    {
        $otherCategory = Category::factory()->create();

        Skill::factory()->create([
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        Skill::factory()->create([
            'category_id' => $otherCategory->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/skills?category_id={$this->category->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($this->category->id, $response->json('data.0.category.id'));
    }

    public function test_can_filter_skills_by_price_range(): void
    {
        Skill::factory()->create([
            'pricing_type' => 'hourly',
            'price_per_hour' => 25.00,
            'is_active' => true,
        ]);

        Skill::factory()->create([
            'pricing_type' => 'hourly',
            'price_per_hour' => 75.00,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/skills?min_price=20&max_price=50');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_search_skills(): void
    {
        Skill::factory()->create([
            'title' => 'WordPress Development',
            'description' => 'Expert WordPress developer',
            'is_active' => true,
        ]);

        Skill::factory()->create([
            'title' => 'React Development',
            'description' => 'Frontend React specialist',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/skills?search=WordPress');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsString('WordPress', $response->json('data.0.title'));
    }

    public function test_can_create_skill(): void
    {
        Sanctum::actingAs($this->user);

        $skillData = [
            'title' => 'Web Development',
            'description' => 'Full-stack web development services',
            'category_id' => $this->category->id,
            'pricing_type' => 'hourly',
            'price_per_hour' => 50.00,
            'is_available' => true,
        ];

        $response = $this->postJson('/api/skills', $skillData);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'pricing_type',
                    'price_per_hour',
                    'user',
                    'category',
                ],
            ]);

        $this->assertDatabaseHas('skills', [
            'title' => 'Web Development',
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
        ]);
    }

    public function test_validates_skill_creation_data(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/skills', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'description', 'category_id', 'pricing_type']);
    }

    public function test_validates_pricing_for_hourly_skills(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/skills', [
            'title' => 'Test Skill',
            'description' => 'Test description',
            'category_id' => $this->category->id,
            'pricing_type' => 'hourly',
            // Missing price_per_hour
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['price_per_hour']);
    }

    public function test_validates_pricing_for_fixed_skills(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/skills', [
            'title' => 'Test Skill',
            'description' => 'Test description',
            'category_id' => $this->category->id,
            'pricing_type' => 'fixed',
            // Missing price_fixed
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['price_fixed']);
    }

    public function test_can_view_skill_details(): void
    {
        $skill = Skill::factory()->create([
            'user_id' => $this->otherUser->id,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/skills/{$skill->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'display_price',
                    'min_price',
                    'user' => [
                        'id',
                        'first_name',
                        'last_name',
                        'avatar',
                        'location',
                        'average_rating',
                        'total_reviews',
                    ],
                    'category' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                    ],
                ],
            ]);
    }

    public function test_cannot_view_inactive_skill_unless_owner(): void
    {
        $skill = Skill::factory()->create([
            'user_id' => $this->otherUser->id,
            'is_active' => false,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/skills/{$skill->id}");

        $response->assertNotFound();
    }

    public function test_can_update_own_skill(): void
    {
        $skill = Skill::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->putJson("/api/skills/{$skill->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Skill updated successfully.',
                'data' => [
                    'title' => 'Updated Title',
                ],
            ]);

        $this->assertDatabaseHas('skills', [
            'id' => $skill->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_cannot_update_others_skill(): void
    {
        $skill = Skill::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->putJson("/api/skills/{$skill->id}", [
            'title' => 'Hacked Title',
        ]);

        $response->assertForbidden();
    }

    public function test_can_delete_own_skill(): void
    {
        $skill = Skill::factory()->create([
            'user_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/skills/{$skill->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Skill deleted successfully.',
            ]);

        $this->assertSoftDeleted('skills', ['id' => $skill->id]);
    }

    public function test_cannot_delete_others_skill(): void
    {
        $skill = Skill::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/skills/{$skill->id}");

        $response->assertForbidden();
    }

    public function test_can_get_skills_by_category(): void
    {
        Skill::factory()->count(2)->create([
            'category_id' => $this->category->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        $otherCategory = Category::factory()->create();
        Skill::factory()->create([
            'category_id' => $otherCategory->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/skills/category/{$this->category->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame($this->category->id, $response->json('category_id'));
    }

    public function test_can_get_own_skills(): void
    {
        // Clear any existing skills first
        Skill::query()->forceDelete();

        Skill::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        Skill::factory()->create([
            'user_id' => $this->otherUser->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/skills/my-skills');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));

        foreach ($response->json('data') as $skill) {
            $this->assertSame($this->user->id, $skill['user']['id']);
        }
    }

    public function test_can_toggle_skill_availability(): void
    {
        $skill = Skill::factory()->create([
            'user_id' => $this->user->id,
            'is_available' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->patchJson("/api/skills/{$skill->id}/toggle-availability");

        $response->assertOk()
            ->assertJson([
                'message' => 'Skill is now unavailable.',
                'is_available' => false,
            ]);

        $this->assertDatabaseHas('skills', [
            'id' => $skill->id,
            'is_available' => false,
        ]);
    }

    public function test_cannot_toggle_others_skill_availability(): void
    {
        $skill = Skill::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->patchJson("/api/skills/{$skill->id}/toggle-availability");

        $response->assertForbidden();
    }

    public function test_search_endpoint_works(): void
    {
        Skill::factory()->create([
            'title' => 'Laravel Development',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/skills/search?search=Laravel');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/skills');
        $response->assertUnauthorized();

        $response = $this->postJson('/api/skills', []);
        $response->assertUnauthorized();
    }

    public function test_pagination_works(): void
    {
        // Clear any existing skills first
        Skill::query()->forceDelete();

        Skill::factory()->count(25)->create([
            'is_active' => true,
            'is_available' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/skills?per_page=10');

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertSame(25, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.last_page'));
    }

    public function test_sorting_works(): void
    {
        // Clear existing skills first
        Skill::query()->forceDelete();

        $skill1 = Skill::factory()->create([
            'title' => 'A Skill',
            'created_at' => now()->subDay(),
            'is_active' => true,
            'is_available' => true,
        ]);

        $skill2 = Skill::factory()->create([
            'title' => 'Z Skill',
            'created_at' => now(),
            'is_active' => true,
            'is_available' => true,
        ]);

        Sanctum::actingAs($this->user);

        // Test ascending sort by created_at
        $response = $this->getJson('/api/skills?sort_by=created_at&sort_direction=asc');

        $response->assertOk();
        $this->assertSame($skill1->id, $response->json('data.0.id'));
        $this->assertSame($skill2->id, $response->json('data.1.id'));
    }

    public function test_location_filtering_works(): void
    {
        // Clear existing skills first
        Skill::query()->forceDelete();

        $userInNY = User::factory()->create([
            'location' => 'New York, NY',
            'email_verified_at' => now(),
        ]);

        $userInLA = User::factory()->create([
            'location' => 'Los Angeles, CA',
            'email_verified_at' => now(),
        ]);

        Skill::factory()->create([
            'user_id' => $userInNY->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        Skill::factory()->create([
            'user_id' => $userInLA->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/skills?location=New York');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsString('New York', $response->json('data.0.user.location'));
    }
}
