<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\CreateReviewRequest;
use App\Http\Requests\Review\ModerateReviewRequest;
use App\Http\Requests\Review\ReportReviewRequest;
use App\Http\Requests\Review\ReviewListRequest;
use App\Http\Requests\Review\ReviewResponseRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * Display a listing of reviews with filtering options.
     */
    public function index(ReviewListRequest $request): JsonResponse
    {
        $query = Review::query()->withRelations();

        // Apply filters
        if ($request->filled('user_id')) {
            $query->forUser($request->integer('user_id'));
        }

        if ($request->filled('job_id')) {
            $query->where('job_id', $request->integer('job_id'));
        }

        if ($request->filled('rating')) {
            $query->withRating($request->integer('rating'));
        }

        if ($request->filled('rating_min') || $request->filled('rating_max')) {
            $minRating = $request->integer('rating_min', Review::MIN_RATING);
            $maxRating = $request->integer('rating_max', Review::MAX_RATING);
            $query->whereBetween('rating', [$minRating, $maxRating]);
        }

        if ($request->filled('is_public')) {
            $isPublic = in_array($request->input('is_public'), ['true', '1', 1, true], true);
            if ($isPublic) {
                $query->public();
            } else {
                $query->where('is_public', false);
            }
        } else {
            // Default to public reviews only for non-admin users
            if (! $request->user() || ! $request->user()->is_admin) {
                $query->public();
            }
        }

        if ($request->filled('is_flagged') && $request->user()?->is_admin) {
            $isFlagged = in_array($request->input('is_flagged'), ['true', '1', 1, true], true);
            if ($isFlagged) {
                $query->flagged();
            } else {
                $query->where('is_flagged', false);
            }
        }

        if ($request->filled('search')) {
            $query->searchComment($request->string('search'));
        }

        if ($request->filled('recent_days')) {
            $query->recent($request->integer('recent_days'));
        }

        // Apply sorting
        $sortBy = $request->string('sort_by', 'created_at');
        $sortDirection = $request->string('sort_direction', 'desc');

        if ($sortBy === 'rating') {
            $query->orderByRating($sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $reviews = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $reviews->items(),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Store a newly created review.
     */
    public function store(CreateReviewRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $review = Review::create($request->validated());

            // Update user rating statistics
            $this->updateUserRatingStats($review->reviewee_id);

            DB::commit();

            $review->load(['reviewer:id,first_name,last_name,avatar', 'reviewee:id,first_name,last_name,avatar', 'job:id,title']);

            return response()->json([
                'success' => true,
                'message' => 'Review created successfully.',
                'data' => $review,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create review.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified review.
     */
    public function show(Review $review): JsonResponse
    {
        $this->authorize('view', $review);

        $review->load(['reviewer:id,first_name,last_name,avatar', 'reviewee:id,first_name,last_name,avatar', 'job:id,title']);

        return response()->json([
            'success' => true,
            'data' => $review,
        ]);
    }

    /**
     * Update the specified review.
     */
    public function update(UpdateReviewRequest $request, Review $review): JsonResponse
    {
        try {
            DB::beginTransaction();

            $oldRating = $review->rating;
            $review->update($request->validated());

            // Update user rating statistics if rating changed
            if ($oldRating !== $review->rating) {
                $this->updateUserRatingStats($review->reviewee_id);
            }

            DB::commit();

            $review->load(['reviewer:id,first_name,last_name,avatar', 'reviewee:id,first_name,last_name,avatar', 'job:id,title']);

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully.',
                'data' => $review,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update review.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified review.
     */
    public function destroy(Review $review): JsonResponse
    {
        $this->authorize('delete', $review);

        try {
            DB::beginTransaction();

            $revieweeId = $review->reviewee_id;
            $review->delete();

            // Update user rating statistics
            $this->updateUserRatingStats($revieweeId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get review statistics for a user.
     */
    public function statistics(Request $request, User $user): JsonResponse
    {
        $this->authorize('viewStatistics', [Review::class, $user]);

        $stats = Review::calculateUserRatingStats($user->id);

        // Add additional statistics
        $recentStats = Review::forUser($user->id)
            ->public()
            ->recent(30)
            ->selectRaw('
                COUNT(*) as recent_reviews_count,
                AVG(rating) as recent_average_rating,
                COUNT(CASE WHEN rating >= 4 THEN 1 END) as recent_positive_count,
                COUNT(CASE WHEN rating <= 2 THEN 1 END) as recent_negative_count
            ')
            ->first();

        $stats['recent_30_days'] = [
            'total_reviews' => $recentStats->recent_reviews_count ?? 0,
            'average_rating' => round($recentStats->recent_average_rating ?? 0, 2),
            'positive_reviews' => $recentStats->recent_positive_count ?? 0,
            'negative_reviews' => $recentStats->recent_negative_count ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Moderate a review (admin only).
     */
    public function moderate(ModerateReviewRequest $request, Review $review): JsonResponse
    {
        try {
            DB::beginTransaction();

            $action = $request->string('action');
            $userId = $request->user()->id;

            switch ($action) {
                case 'approve':
                    $review->moderate($userId, true);
                    $message = 'Review approved successfully.';

                    break;

                case 'reject':
                    $review->moderate($userId, false);
                    $message = 'Review rejected successfully.';

                    break;

                case 'hide':
                    $review->update(['is_public' => false]);
                    $message = 'Review hidden successfully.';

                    break;

                case 'show':
                    $review->update(['is_public' => true]);
                    $message = 'Review made public successfully.';

                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid moderation action.',
                    ], 400);
            }

            // Update user rating statistics
            $this->updateUserRatingStats($review->reviewee_id);

            DB::commit();

            $review->load(['reviewer:id,first_name,last_name,avatar', 'reviewee:id,first_name,last_name,avatar', 'job:id,title']);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $review,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to moderate review.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Report a review for inappropriate content.
     */
    public function report(ReportReviewRequest $request, Review $review): JsonResponse
    {
        try {
            $reason = $request->string('reason');

            if (! $review->flag($reason)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to report this review.',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Review reported successfully. It will be reviewed by our moderation team.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to report review.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Add a response to a review.
     */
    public function respond(ReviewResponseRequest $request, Review $review): JsonResponse
    {
        try {
            $review->update([
                'response' => $request->string('response'),
                'response_at' => now(),
            ]);

            $review->load(['reviewer:id,first_name,last_name,avatar', 'reviewee:id,first_name,last_name,avatar', 'job:id,title']);

            return response()->json([
                'success' => true,
                'message' => 'Response added successfully.',
                'data' => $review,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add response.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get reviews that need moderation (admin only).
     */
    public function needingModeration(Request $request): JsonResponse
    {
        $this->authorize('moderate', Review::class);

        $reviews = Review::needingModeration()
            ->withRelations()
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $reviews->items(),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Get aggregated review statistics for the platform (admin only).
     */
    public function platformStatistics(Request $request): JsonResponse
    {
        $this->authorize('moderate', Review::class);

        $stats = Review::selectRaw('
            COUNT(*) as total_reviews,
            COUNT(CASE WHEN is_public = 1 THEN 1 END) as public_reviews,
            COUNT(CASE WHEN is_flagged = 1 THEN 1 END) as flagged_reviews,
            COUNT(CASE WHEN moderated_at IS NOT NULL THEN 1 END) as moderated_reviews,
            AVG(rating) as average_rating,
            COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star_count,
            COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star_count,
            COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star_count,
            COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star_count,
            COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star_count
        ')->first();

        $recentStats = Review::recent(30)
            ->selectRaw('
                COUNT(*) as recent_total,
                AVG(rating) as recent_average,
                COUNT(CASE WHEN is_flagged = 1 THEN 1 END) as recent_flagged
            ')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'total_reviews' => $stats->total_reviews ?? 0,
                'public_reviews' => $stats->public_reviews ?? 0,
                'flagged_reviews' => $stats->flagged_reviews ?? 0,
                'moderated_reviews' => $stats->moderated_reviews ?? 0,
                'average_rating' => round($stats->average_rating ?? 0, 2),
                'rating_distribution' => [
                    5 => $stats->five_star_count ?? 0,
                    4 => $stats->four_star_count ?? 0,
                    3 => $stats->three_star_count ?? 0,
                    2 => $stats->two_star_count ?? 0,
                    1 => $stats->one_star_count ?? 0,
                ],
                'recent_30_days' => [
                    'total_reviews' => $recentStats->recent_total ?? 0,
                    'average_rating' => round($recentStats->recent_average ?? 0, 2),
                    'flagged_reviews' => $recentStats->recent_flagged ?? 0,
                ],
            ],
        ]);
    }

    /**
     * Update user rating statistics.
     */
    private function updateUserRatingStats(int $userId): void
    {
        $stats = Review::calculateUserRatingStats($userId);

        User::where('id', $userId)->update([
            'average_rating' => $stats['average_rating'],
            'total_reviews' => $stats['total_reviews'],
        ]);
    }
}
