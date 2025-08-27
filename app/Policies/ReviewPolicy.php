<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReviewPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All users can view public reviews
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Review $review): bool
    {
        // Users can view public reviews or their own reviews (given/received)
        return $review->is_public ||
               $user->id === $review->reviewer_id ||
               $user->id === $review->reviewee_id ||
               $user->is_admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only verified and active users can create reviews
        return $user->is_active && $user->email_verified_at !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Review $review): bool
    {
        // Only reviewer can update their review within 24 hours
        return $user->id === $review->reviewer_id &&
               $review->created_at->diffInHours(now()) < 24;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Review $review): bool
    {
        // Reviewer can delete within 24 hours, admins can delete any review
        $canDeleteOwn = $user->id === $review->reviewer_id &&
                       $review->created_at->diffInHours(now()) < 24;

        return $canDeleteOwn || $user->is_admin;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Review $review): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Review $review): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can create a review for a specific job.
     */
    public function createForJob(User $user, Job $job): bool
    {
        // Job must be completed
        if ($job->status !== 'completed') {
            return false;
        }

        // User must be either the job owner or the assigned worker
        if (! in_array($user->id, [$job->user_id, $job->assigned_to])) {
            return false;
        }

        // Check if user has already reviewed this job
        $existingReview = Review::where('job_id', $job->id)
            ->where('reviewer_id', $user->id)
            ->exists();

        return ! $existingReview;
    }

    /**
     * Determine whether the user can review another user for a specific job.
     */
    public function reviewUser(User $reviewer, User $reviewee, Job $job): bool
    {
        // Job must be completed
        if ($job->status !== 'completed') {
            return false;
        }

        // Reviewer must be involved in the job
        if (! in_array($reviewer->id, [$job->user_id, $job->assigned_to])) {
            return false;
        }

        // Reviewee must be the other party in the job
        if ($reviewer->id === $job->user_id) {
            // Job owner reviewing the worker
            return $reviewee->id === $job->assigned_to;
        } else {
            // Worker reviewing the job owner
            return $reviewee->id === $job->user_id;
        }
    }

    /**
     * Determine whether the user can respond to a review.
     */
    public function respond(User $user, Review $review): bool
    {
        // Only the reviewee can respond to reviews about them
        return $user->id === $review->reviewee_id;
    }

    /**
     * Determine whether the user can report a review.
     */
    public function report(User $user, Review $review): bool
    {
        // Users can report reviews (except their own)
        return $user->id !== $review->reviewer_id &&
               $user->is_active &&
               $user->email_verified_at !== null;
    }

    /**
     * Determine whether the user can moderate reviews.
     */
    public function moderate(User $user, ?Review $review = null): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view review statistics.
     */
    public function viewStatistics(User $user, User $reviewee): bool
    {
        // All users can view public review statistics
        return $reviewee->is_active;
    }

    /**
     * Determine whether the user can hide/show a review.
     */
    public function toggleVisibility(User $user, Review $review): bool
    {
        // Only reviewee can hide reviews about them, admins can toggle any
        return $user->id === $review->reviewee_id || $user->is_admin;
    }

    /**
     * Determine whether the user can flag a review as helpful.
     */
    public function markHelpful(User $user, Review $review): bool
    {
        // Users can mark reviews as helpful (except their own)
        return $user->id !== $review->reviewer_id &&
               $review->is_public &&
               $user->is_active &&
               $user->email_verified_at !== null;
    }
}
