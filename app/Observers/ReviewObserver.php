<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RatingHistory;
use App\Models\Review;
use App\Services\RatingService;
use Illuminate\Support\Facades\Log;

class ReviewObserver
{
    /**
     * Rating service instance.
     */
    private RatingService $ratingService;

    /**
     * Create a new observer instance.
     */
    public function __construct(RatingService $ratingService)
    {
        $this->ratingService = $ratingService;
    }

    /**
     * Handle the Review "created" event.
     */
    public function created(Review $review): void
    {
        $this->updateRatingCache($review, 'new_review');
    }

    /**
     * Handle the Review "updated" event.
     */
    public function updated(Review $review): void
    {
        // Only update cache if rating-related fields changed
        if ($review->wasChanged(['rating', 'is_public', 'is_flagged'])) {
            $this->updateRatingCache($review, 'review_updated');
        }
    }

    /**
     * Handle the Review "deleted" event.
     */
    public function deleted(Review $review): void
    {
        $this->updateRatingCache($review, 'review_deleted');
    }

    /**
     * Handle the Review "restored" event.
     */
    public function restored(Review $review): void
    {
        $this->updateRatingCache($review, 'review_restored');
    }

    /**
     * Update rating cache for the reviewee.
     */
    private function updateRatingCache(Review $review, string $trigger): void
    {
        try {
            // Invalidate cache first
            $this->ratingService->invalidateUserCache($review->reviewee_id);

            // Calculate new stats
            $stats = $this->ratingService->calculateUserRatingStats($review->reviewee_id, false);

            // Update user's cached rating
            $review->reviewee->updateRatingCache($stats);

            // Create rating history entry for significant changes
            if ($this->shouldCreateHistoryEntry($review, $stats, $trigger)) {
                RatingHistory::createFromStats($review->reviewee_id, $stats, $trigger);
            }

            // Also invalidate reviewer's cache if they have reviews
            if ($review->reviewer->receivedReviews()->exists()) {
                $this->ratingService->invalidateUserCache($review->reviewer_id);
            }

        } catch (\Exception $e) {
            Log::error('Failed to update rating cache', [
                'review_id' => $review->id,
                'reviewee_id' => $review->reviewee_id,
                'trigger' => $trigger,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determine if a rating history entry should be created.
     */
    private function shouldCreateHistoryEntry(Review $review, array $stats, string $trigger): bool
    {
        // Always create history for new reviews
        if ($trigger === 'new_review') {
            return true;
        }

        // For updates, only create history if there's a significant change
        $lastHistory = RatingHistory::where('user_id', $review->reviewee_id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $lastHistory) {
            return true;
        }

        // Check if weighted average changed significantly
        $ratingChange = abs($stats['weighted_average'] - $lastHistory->weighted_average);
        $qualityChange = abs($stats['quality_score'] - $lastHistory->quality_score);

        return $ratingChange >= 0.2 || $qualityChange >= 5.0;
    }
}
