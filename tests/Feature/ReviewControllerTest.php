<?php

declare(strict_types=1);

use App\Models\Job;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => false]);
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->worker = User::factory()->create(['is_admin' => false]);

    $this->job = Job::factory()->create([
        'user_id' => $this->user->id,
        'assigned_to' => $this->worker->id,
        'status' => 'completed',
    ]);
});

describe('Review Creation', function () {
    it('can create a review for completed job', function () {
        $reviewData = [
            'job_id' => $this->job->id,
            'reviewee_id' => $this->worker->id,
            'rating' => 5,
            'comment' => 'Excellent work! Very professional and delivered on time.',
            'is_public' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/reviews', $reviewData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Review created successfully.',
            ]);

        $this->assertDatabaseHas('reviews', [
            'job_id' => $this->job->id,
            'reviewer_id' => $this->user->id,
            'reviewee_id' => $this->worker->id,
            'rating' => 5,
            'comment' => 'Excellent work! Very professional and delivered on time.',
        ]);
    });

    it('cannot create duplicate review for same job', function () {
        Review::factory()->create([
            'job_id' => $this->job->id,
            'reviewer_id' => $this->user->id,
            'reviewee_id' => $this->worker->id,
        ]);

        $reviewData = [
            'job_id' => $this->job->id,
            'reviewee_id' => $this->worker->id,
            'rating' => 4,
            'comment' => 'Good work overall.',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/reviews', $reviewData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['job_id']);
    });

    it('cannot create review for incomplete job', function () {
        $incompleteJob = Job::factory()->create([
            'user_id' => $this->user->id,
            'assigned_to' => $this->worker->id,
            'status' => 'in_progress',
        ]);

        $reviewData = [
            'job_id' => $incompleteJob->id,
            'reviewee_id' => $this->worker->id,
            'rating' => 5,
            'comment' => 'Great work!',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/reviews', $reviewData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['job_id']);
    });

    it('validates rating range', function () {
        $reviewData = [
            'job_id' => $this->job->id,
            'reviewee_id' => $this->worker->id,
            'rating' => 6, // Invalid rating
            'comment' => 'Good work.',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/reviews', $reviewData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    });

    it('validates comment length', function () {
        $reviewData = [
            'job_id' => $this->job->id,
            'reviewee_id' => $this->worker->id,
            'rating' => 5,
            'comment' => 'Short', // Too short
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/reviews', $reviewData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['comment']);
    });
});

describe('Review Listing', function () {
    beforeEach(function () {
        Review::factory()->count(5)->create([
            'reviewee_id' => $this->worker->id,
            'is_public' => true,
        ]);

        Review::factory()->count(2)->create([
            'reviewee_id' => $this->worker->id,
            'is_public' => false,
        ]);
    });

    it('can list public reviews', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/reviews');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(5, 'data');
    });

    it('can filter reviews by user', function () {
        $response = $this->actingAs($this->user)
            ->getJson("/api/reviews?user_id={$this->worker->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $reviews = $response->json('data');
        foreach ($reviews as $review) {
            expect($review['reviewee_id'])->toBe($this->worker->id);
        }
    });

    it('can filter reviews by rating', function () {
        Review::factory()->create([
            'reviewee_id' => $this->worker->id,
            'rating' => 5,
            'is_public' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/reviews?rating=5');

        $response->assertStatus(200);

        $reviews = $response->json('data');
        foreach ($reviews as $review) {
            expect($review['rating'])->toBe(5);
        }
    });

    it('can search reviews by comment', function () {
        Review::factory()->create([
            'reviewee_id' => $this->worker->id,
            'comment' => 'Excellent professional service',
            'is_public' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/reviews?search=professional');

        $response->assertStatus(200);

        $reviews = $response->json('data');
        expect(count($reviews))->toBeGreaterThan(0);
    });

    it('admin can see private reviews', function () {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/reviews?is_public=false');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    });
});

describe('Review Display', function () {
    it('can show public review', function () {
        $review = Review::factory()->create([
            'reviewee_id' => $this->worker->id,
            'is_public' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/reviews/{$review->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                ],
            ]);
    });

    it('cannot show private review to unauthorized user', function () {
        $review = Review::factory()->create([
            'reviewee_id' => $this->worker->id,
            'reviewer_id' => $this->admin->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/reviews/{$review->id}");

        $response->assertStatus(403);
    });
});

describe('Review Updates', function () {
    it('can update own review within 24 hours', function () {
        $review = Review::factory()->create([
            'reviewer_id' => $this->user->id,
            'reviewee_id' => $this->worker->id,
            'created_at' => now()->subHours(12),
        ]);

        $updateData = [
            'rating' => 4,
            'comment' => 'Updated comment with more details.',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/reviews/{$review->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Review updated successfully.',
            ]);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 4,
            'comment' => 'Updated comment with more details.',
        ]);
    });

    it('cannot update review after 24 hours', function () {
        $review = Review::factory()->create([
            'reviewer_id' => $this->user->id,
            'reviewee_id' => $this->worker->id,
            'created_at' => now()->subHours(25),
        ]);

        $updateData = [
            'rating' => 4,
            'comment' => 'Trying to update old review.',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/reviews/{$review->id}", $updateData);

        $response->assertStatus(403);
    });

    it('cannot update other users review', function () {
        $review = Review::factory()->create([
            'reviewer_id' => $this->admin->id,
            'reviewee_id' => $this->worker->id,
        ]);

        $updateData = [
            'rating' => 1,
            'comment' => 'Malicious update attempt.',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/reviews/{$review->id}", $updateData);

        $response->assertStatus(403);
    });
});

describe('Review Deletion', function () {
    it('can delete own review within 24 hours', function () {
        $review = Review::factory()->create([
            'reviewer_id' => $this->user->id,
            'reviewee_id' => $this->worker->id,
            'created_at' => now()->subHours(12),
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reviews/{$review->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Review deleted successfully.',
            ]);

        $this->assertSoftDeleted('reviews', ['id' => $review->id]);
    });

    it('admin can delete any review', function () {
        $review = Review::factory()->create([
            'reviewer_id' => $this->user->id,
            'reviewee_id' => $this->worker->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/reviews/{$review->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('reviews', ['id' => $review->id]);
    });
});

describe('Review Statistics', function () {
    beforeEach(function () {
        Review::factory()->count(3)->create([
            'reviewee_id' => $this->worker->id,
            'rating' => 5,
            'is_public' => true,
        ]);

        Review::factory()->count(2)->create([
            'reviewee_id' => $this->worker->id,
            'rating' => 4,
            'is_public' => true,
        ]);
    });

    it('can get user review statistics', function () {
        $response = $this->actingAs($this->user)
            ->getJson("/api/reviews/user/{$this->worker->id}/statistics");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_reviews' => 5,
                    'average_rating' => 4.6,
                ],
            ]);
    });

    it('admin can get platform statistics', function () {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/reviews/platform-statistics');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'total_reviews',
                    'public_reviews',
                    'average_rating',
                    'rating_distribution',
                ],
            ]);
    });

    it('non-admin cannot access platform statistics', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/reviews/platform-statistics');

        $response->assertStatus(403);
    });
});

describe('Review Moderation', function () {
    it('admin can approve flagged review', function () {
        $review = Review::factory()->create([
            'reviewee_id' => $this->worker->id,
            'is_flagged' => true,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/reviews/{$review->id}/moderate", [
                'action' => 'approve',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Review approved successfully.',
            ]);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'is_flagged' => false,
            'is_public' => true,
        ]);
    });

    it('admin can reject flagged review', function () {
        $review = Review::factory()->create([
            'reviewee_id' => $this->worker->id,
            'is_flagged' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/reviews/{$review->id}/moderate", [
                'action' => 'reject',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'is_public' => false,
        ]);
    });

    it('non-admin cannot moderate reviews', function () {
        $review = Review::factory()->create([
            'reviewee_id' => $this->worker->id,
            'is_flagged' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/reviews/{$review->id}/moderate", [
                'action' => 'approve',
            ]);

        $response->assertStatus(403);
    });

    it('admin can get reviews needing moderation', function () {
        Review::factory()->count(3)->create([
            'is_flagged' => true,
            'moderated_at' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reviews/needing-moderation');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });
});

describe('Review Reporting', function () {
    it('can report inappropriate review', function () {
        $review = Review::factory()->create([
            'reviewee_id' => $this->worker->id,
            'reviewer_id' => $this->admin->id,
            'is_public' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/reviews/{$review->id}/report", [
                'reason' => 'inappropriate',
                'description' => 'This review contains inappropriate language.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Review reported successfully. It will be reviewed by our moderation team.',
            ]);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'is_flagged' => true,
            'flagged_reason' => 'inappropriate',
            'is_public' => false,
        ]);
    });

    it('cannot report own review', function () {
        $review = Review::factory()->create([
            'reviewer_id' => $this->user->id,
            'reviewee_id' => $this->worker->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/reviews/{$review->id}/report", [
                'reason' => 'spam',
            ]);

        $response->assertStatus(403);
    });
});

describe('Review Responses', function () {
    it('reviewee can respond to review', function () {
        $review = Review::factory()->create([
            'reviewer_id' => $this->user->id,
            'reviewee_id' => $this->worker->id,
        ]);

        $response = $this->actingAs($this->worker)
            ->postJson("/api/reviews/{$review->id}/respond", [
                'response' => 'Thank you for the positive feedback! It was a pleasure working with you.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Response added successfully.',
            ]);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'response' => 'Thank you for the positive feedback! It was a pleasure working with you.',
        ]);
    });

    it('only reviewee can respond to review', function () {
        $review = Review::factory()->create([
            'reviewer_id' => $this->user->id,
            'reviewee_id' => $this->worker->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/reviews/{$review->id}/respond", [
                'response' => 'Trying to respond to my own review.',
            ]);

        $response->assertStatus(403);
    });
});

describe('Authentication', function () {
    it('requires authentication for creating reviews', function () {
        $response = $this->postJson('/api/reviews', [
            'job_id' => $this->job->id,
            'reviewee_id' => $this->worker->id,
            'rating' => 5,
            'comment' => 'Great work!',
        ]);

        $response->assertStatus(401);
    });

    it('allows unauthenticated users to view public reviews', function () {
        Review::factory()->create([
            'reviewee_id' => $this->worker->id,
            'is_public' => true,
        ]);

        $response = $this->getJson('/api/reviews');

        $response->assertStatus(200);
    });
});
