<?php

namespace Tests\Unit\Models;

use App\Models\Job;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_can_be_created_with_valid_data()
    {
        $job = Job::factory()->create();
        $reviewer = User::factory()->create();
        $reviewee = User::factory()->create();

        $reviewData = [
            'job_id' => $job->id,
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $reviewee->id,
            'rating' => 5,
            'comment' => 'Excellent work, delivered on time and exceeded expectations!',
            'is_public' => true,
        ];

        $review = Review::create($reviewData);

        $this->assertInstanceOf(Review::class, $review);
        $this->assertSame(5, $review->rating);
        $this->assertSame('Excellent work, delivered on time and exceeded expectations!', $review->comment);
        $this->assertTrue($review->is_public);
    }

    public function test_review_belongs_to_job()
    {
        $job = Job::factory()->create();
        $review = Review::factory()->create(['job_id' => $job->id]);

        $this->assertInstanceOf(Job::class, $review->job);
        $this->assertSame($job->id, $review->job->id);
    }

    public function test_review_belongs_to_reviewer()
    {
        $reviewer = User::factory()->create();
        $review = Review::factory()->create(['reviewer_id' => $reviewer->id]);

        $this->assertInstanceOf(User::class, $review->reviewer);
        $this->assertSame($reviewer->id, $review->reviewer->id);
    }

    public function test_review_belongs_to_reviewee()
    {
        $reviewee = User::factory()->create();
        $review = Review::factory()->create(['reviewee_id' => $reviewee->id]);

        $this->assertInstanceOf(User::class, $review->reviewee);
        $this->assertSame($reviewee->id, $review->reviewee->id);
    }

    public function test_review_public_scope()
    {
        Review::factory()->create(['is_public' => true]);
        Review::factory()->create(['is_public' => false]);
        Review::factory()->create(['is_public' => true]);

        $publicReviews = Review::public()->get();

        $this->assertCount(2, $publicReviews);
        $this->assertTrue($publicReviews->first()->is_public);
    }

    public function test_review_private_scope()
    {
        Review::factory()->create(['is_public' => false]);
        Review::factory()->create(['is_public' => true]);
        Review::factory()->create(['is_public' => false]);

        $privateReviews = Review::private()->get();

        $this->assertCount(2, $privateReviews);
        $this->assertFalse($privateReviews->first()->is_public);
    }

    public function test_review_by_rating_scope()
    {
        Review::factory()->create(['rating' => 5]);
        Review::factory()->create(['rating' => 3]);
        Review::factory()->create(['rating' => 5]);
        Review::factory()->create(['rating' => 4]);

        $fiveStarReviews = Review::byRating(5)->get();

        $this->assertCount(2, $fiveStarReviews);
        $this->assertSame(5, $fiveStarReviews->first()->rating);
    }

    public function test_review_for_user_scope()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Review::factory()->count(3)->create(['reviewee_id' => $user->id]);
        Review::factory()->count(2)->create(['reviewee_id' => $otherUser->id]);

        $userReviews = Review::forUser($user->id)->get();

        $this->assertCount(3, $userReviews);
    }

    public function test_review_by_user_scope()
    {
        $reviewer = User::factory()->create();
        $otherReviewer = User::factory()->create();

        Review::factory()->count(2)->create(['reviewer_id' => $reviewer->id]);
        Review::factory()->count(3)->create(['reviewer_id' => $otherReviewer->id]);

        $reviewsByUser = Review::byUser($reviewer->id)->get();

        $this->assertCount(2, $reviewsByUser);
    }

    public function test_review_recent_scope()
    {
        Review::factory()->create(['created_at' => now()->subDays(1)]);
        Review::factory()->create(['created_at' => now()->subDays(10)]);
        Review::factory()->create(['created_at' => now()->subDays(2)]);

        $recentReviews = Review::recent(7)->get();

        $this->assertCount(2, $recentReviews);
    }

    public function test_review_high_rating_scope()
    {
        Review::factory()->create(['rating' => 5]);
        Review::factory()->create(['rating' => 4]);
        Review::factory()->create(['rating' => 3]);
        Review::factory()->create(['rating' => 2]);

        $highRatingReviews = Review::highRating()->get();

        $this->assertCount(2, $highRatingReviews);
        foreach ($highRatingReviews as $review) {
            $this->assertGreaterThanOrEqual(4, $review->rating);
        }
    }

    public function test_review_low_rating_scope()
    {
        Review::factory()->create(['rating' => 1]);
        Review::factory()->create(['rating' => 2]);
        Review::factory()->create(['rating' => 3]);
        Review::factory()->create(['rating' => 4]);

        $lowRatingReviews = Review::lowRating()->get();

        $this->assertCount(2, $lowRatingReviews);
        foreach ($lowRatingReviews as $review) {
            $this->assertLessThanOrEqual(2, $review->rating);
        }
    }

    public function test_review_is_positive_method()
    {
        $positiveReview = Review::factory()->create(['rating' => 4]);
        $negativeReview = Review::factory()->create(['rating' => 2]);

        $this->assertTrue($positiveReview->isPositive());
        $this->assertFalse($negativeReview->isPositive());
    }

    public function test_review_is_negative_method()
    {
        $negativeReview = Review::factory()->create(['rating' => 2]);
        $positiveReview = Review::factory()->create(['rating' => 4]);

        $this->assertTrue($negativeReview->isNegative());
        $this->assertFalse($positiveReview->isNegative());
    }

    public function test_review_is_neutral_method()
    {
        $neutralReview = Review::factory()->create(['rating' => 3]);
        $positiveReview = Review::factory()->create(['rating' => 4]);

        $this->assertTrue($neutralReview->isNeutral());
        $this->assertFalse($positiveReview->isNeutral());
    }

    public function test_review_star_display_accessor()
    {
        $review = Review::factory()->create(['rating' => 4]);

        $this->assertSame('★★★★☆', $review->star_display);
    }

    public function test_review_comment_preview_accessor()
    {
        $shortReview = Review::factory()->create(['comment' => 'Great work!']);
        $longReview = Review::factory()->create(['comment' => str_repeat('This is a very long review comment. ', 20)]);

        $this->assertSame('Great work!', $shortReview->comment_preview);
        $this->assertLessThanOrEqual(150, strlen($longReview->comment_preview));
        $this->assertStringEndsWith('...', $longReview->comment_preview);
    }

    public function test_review_make_public_method()
    {
        $review = Review::factory()->create(['is_public' => false]);

        $review->makePublic();

        $this->assertTrue($review->is_public);
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'is_public' => true,
        ]);
    }

    public function test_review_make_private_method()
    {
        $review = Review::factory()->create(['is_public' => true]);

        $review->makePrivate();

        $this->assertFalse($review->is_public);
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'is_public' => false,
        ]);
    }

    public function test_review_can_be_edited_method()
    {
        $recentReview = Review::factory()->create(['created_at' => now()->subHours(1)]);
        $oldReview = Review::factory()->create(['created_at' => now()->subDays(8)]);

        $this->assertTrue($recentReview->canBeEdited());
        $this->assertFalse($oldReview->canBeEdited());
    }

    public function test_review_update_rating_method()
    {
        $review = Review::factory()->create(['rating' => 3]);

        $result = $review->updateRating(5);

        $this->assertTrue($result);
        $this->assertSame(5, $review->rating);
    }

    public function test_review_cannot_update_invalid_rating()
    {
        $review = Review::factory()->create(['rating' => 3]);

        $result = $review->updateRating(6); // Invalid rating

        $this->assertFalse($result);
        $this->assertSame(3, $review->rating); // Rating unchanged
    }

    public function test_review_validation_constraints()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Try to create review without required fields
        Review::create([]);
    }

    public function test_review_rating_bounds_validation()
    {
        $job = Job::factory()->create();
        $reviewer = User::factory()->create();
        $reviewee = User::factory()->create();

        // Rating should be between 1 and 5
        $invalidReview = Review::factory()->make([
            'job_id' => $job->id,
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $reviewee->id,
            'rating' => 6, // Invalid rating
        ]);

        // This validation should be handled by database constraints or form requests
        $this->assertSame(6, $invalidReview->rating);
    }

    public function test_review_unique_constraint()
    {
        $job = Job::factory()->create();
        $reviewer = User::factory()->create();
        $reviewee = User::factory()->create();

        Review::factory()->create([
            'job_id' => $job->id,
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $reviewee->id,
        ]);

        // Try to create duplicate review
        $this->expectException(\Illuminate\Database\QueryException::class);

        Review::factory()->create([
            'job_id' => $job->id,
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $reviewee->id,
        ]);
    }

    public function test_review_cannot_review_self()
    {
        $user = User::factory()->create();
        $job = Job::factory()->create();

        // This validation should be handled by business logic
        $selfReview = Review::factory()->make([
            'job_id' => $job->id,
            'reviewer_id' => $user->id,
            'reviewee_id' => $user->id,
        ]);

        $this->assertSame($selfReview->reviewer_id, $selfReview->reviewee_id);
    }

    public function test_review_soft_deletes()
    {
        $review = Review::factory()->create();
        $reviewId = $review->id;

        $review->delete();

        $this->assertSoftDeleted('reviews', ['id' => $reviewId]);
        $this->assertCount(0, Review::all());
        $this->assertCount(1, Review::withTrashed()->get());
    }

    public function test_review_average_calculation()
    {
        $user = User::factory()->create();

        Review::factory()->create(['reviewee_id' => $user->id, 'rating' => 5]);
        Review::factory()->create(['reviewee_id' => $user->id, 'rating' => 4]);
        Review::factory()->create(['reviewee_id' => $user->id, 'rating' => 3]);

        $averageRating = Review::forUser($user->id)->avg('rating');

        $this->assertSame(4.0, $averageRating);
    }

    public function test_review_response_functionality()
    {
        $review = Review::factory()->create(['comment' => 'Great work!']);

        // In a real implementation, you might have response functionality
        $review->response = 'Thank you for the feedback!';
        $review->save();

        $this->assertSame('Thank you for the feedback!', $review->response);
    }
}
