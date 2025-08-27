<?php

declare(strict_types=1);

use App\Models\SavedSearch;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('can list user saved searches', function () {
    SavedSearch::factory()->count(3)->create(['user_id' => $this->user->id]);
    SavedSearch::factory()->count(2)->create(); // Other user's searches

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/saved-searches');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('can filter saved searches by type', function () {
    SavedSearch::factory()->forJobs()->count(2)->create(['user_id' => $this->user->id]);
    SavedSearch::factory()->forSkills()->count(1)->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/saved-searches?type=jobs');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('can create a saved search', function () {
    $searchData = [
        'name' => 'Web Development Jobs',
        'type' => 'jobs',
        'filters' => [
            'query' => 'web development',
            'category_id' => 1,
            'budget_min' => 100,
            'budget_max' => 500,
        ],
        'sort_options' => [
            'sort_by' => 'relevance',
            'direction' => 'desc',
        ],
        'notifications_enabled' => true,
        'notification_frequency' => 24,
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/saved-searches', $searchData);

    $response->assertCreated()
        ->assertJsonFragment(['name' => 'Web Development Jobs']);

    $this->assertDatabaseHas('saved_searches', [
        'user_id' => $this->user->id,
        'name' => 'Web Development Jobs',
        'type' => 'jobs',
    ]);
});

test('cannot create duplicate saved search name for same type', function () {
    SavedSearch::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Web Development Jobs',
        'type' => 'jobs',
    ]);

    $searchData = [
        'name' => 'Web Development Jobs',
        'type' => 'jobs',
        'filters' => ['query' => 'web'],
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/saved-searches', $searchData);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('can create same name for different types', function () {
    SavedSearch::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Development',
        'type' => 'jobs',
    ]);

    $searchData = [
        'name' => 'Development',
        'type' => 'skills',
        'filters' => ['query' => 'development'],
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/saved-searches', $searchData);

    $response->assertCreated();
});

test('can view a saved search', function () {
    $savedSearch = SavedSearch::factory()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/saved-searches/{$savedSearch->id}");

    $response->assertOk()
        ->assertJsonFragment(['id' => $savedSearch->id]);
});

test('cannot view other users saved search', function () {
    $otherUser = User::factory()->create();
    $savedSearch = SavedSearch::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/saved-searches/{$savedSearch->id}");

    $response->assertForbidden();
});

test('can execute a saved search', function () {
    $savedSearch = SavedSearch::factory()->forJobs()->create([
        'user_id' => $this->user->id,
        'filters' => ['query' => 'test'],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/saved-searches/{$savedSearch->id}/execute");

    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'meta',
            'search_info' => ['name', 'description', 'filters'],
        ]);
});

test('can get new results for saved search', function () {
    $savedSearch = SavedSearch::factory()->create([
        'user_id' => $this->user->id,
        'last_notification_sent' => now()->subHours(2),
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/saved-searches/{$savedSearch->id}/new-results");

    $response->assertOk()
        ->assertJsonStructure(['data', 'count', 'last_check']);
});

test('can update a saved search', function () {
    $savedSearch = SavedSearch::factory()->create(['user_id' => $this->user->id]);

    $updateData = [
        'name' => 'Updated Search Name',
        'notifications_enabled' => false,
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/saved-searches/{$savedSearch->id}", $updateData);

    $response->assertOk()
        ->assertJsonFragment(['name' => 'Updated Search Name']);

    $this->assertDatabaseHas('saved_searches', [
        'id' => $savedSearch->id,
        'name' => 'Updated Search Name',
        'notifications_enabled' => false,
    ]);
});

test('can delete a saved search', function () {
    $savedSearch = SavedSearch::factory()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/saved-searches/{$savedSearch->id}");

    $response->assertOk();

    $this->assertDatabaseMissing('saved_searches', ['id' => $savedSearch->id]);
});

test('can toggle notifications for saved search', function () {
    $savedSearch = SavedSearch::factory()->create([
        'user_id' => $this->user->id,
        'notifications_enabled' => true,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/saved-searches/{$savedSearch->id}/toggle-notifications");

    $response->assertOk()
        ->assertJsonFragment(['notifications_enabled' => false]);

    $this->assertDatabaseHas('saved_searches', [
        'id' => $savedSearch->id,
        'notifications_enabled' => false,
    ]);
});

test('can get filter options', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/saved-searches/filter-options?type=jobs');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'categories',
                'price_ranges',
                'locations',
                'ratings',
            ],
        ]);
});

test('requires authentication for all endpoints', function () {
    $savedSearch = SavedSearch::factory()->create();

    $endpoints = [
        ['GET', '/api/saved-searches'],
        ['POST', '/api/saved-searches'],
        ['GET', "/api/saved-searches/{$savedSearch->id}"],
        ['PUT', "/api/saved-searches/{$savedSearch->id}"],
        ['DELETE', "/api/saved-searches/{$savedSearch->id}"],
        ['POST', "/api/saved-searches/{$savedSearch->id}/execute"],
        ['GET', "/api/saved-searches/{$savedSearch->id}/new-results"],
        ['PATCH', "/api/saved-searches/{$savedSearch->id}/toggle-notifications"],
        ['GET', '/api/saved-searches/filter-options?type=jobs'],
    ];

    foreach ($endpoints as [$method, $url]) {
        $response = $this->json($method, $url);
        $response->assertUnauthorized();
    }
});

test('validates saved search creation data', function () {
    $invalidData = [
        'name' => '', // Required
        'type' => 'invalid', // Must be jobs or skills
        'filters' => 'not-array', // Must be array
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/saved-searches', $invalidData);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'type', 'filters']);
});
