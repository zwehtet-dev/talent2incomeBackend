<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasFactory;
    use SoftDeletes;
    use \App\Traits\CacheInvalidation;

    /**
     * Rating constants.
     */
    public const MIN_RATING = 1;
    public const MAX_RATING = 5;

    /**
     * Flag reasons.
     */
    public const FLAG_INAPPROPRIATE = 'inappropriate';
    public const FLAG_SPAM = 'spam';
    public const FLAG_FAKE = 'fake';
    public const FLAG_OFFENSIVE = 'offensive';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'job_id',
        'reviewer_id',
        'reviewee_id',
        'rating',
        'comment',
        'is_public',
        'is_flagged',
        'flagged_reason',
        'moderated_at',
        'moderated_by',
        'response',
        'response_at',
    ];

    /**
     * Get valid flag reasons.
     */
    public static function getValidFlagReasons(): array
    {
        return [
            self::FLAG_INAPPROPRIATE,
            self::FLAG_SPAM,
            self::FLAG_FAKE,
            self::FLAG_OFFENSIVE,
        ];
    }

    /**
     * The job this review is for.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * The user who wrote this review.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * The user being reviewed.
     */
    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }

    /**
     * The moderator who moderated this review.
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /**
     * Get the rating as stars (for display).
     */
    public function getStarsAttribute(): string
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', self::MAX_RATING - $this->rating);
    }

    /**
     * Check if the rating is positive (4 or 5 stars).
     */
    public function getIsPositiveAttribute(): bool
    {
        return $this->rating >= 4;
    }

    /**
     * Check if the rating is negative (1 or 2 stars).
     */
    public function getIsNegativeAttribute(): bool
    {
        return $this->rating <= 2;
    }

    /**
     * Check if the rating is neutral (3 stars).
     */
    public function getIsNeutralAttribute(): bool
    {
        return $this->rating === 3;
    }

    /**
     * Get a truncated version of the comment.
     */
    public function getCommentPreviewAttribute(): string
    {
        if (! $this->comment) {
            return '';
        }

        if (strlen($this->comment) <= 150) {
            return $this->comment;
        }

        return substr($this->comment, 0, 147) . '...';
    }

    /**
     * Check if review can be edited.
     */
    public function canBeEdited(): bool
    {
        // Reviews can be edited within 24 hours of creation
        return $this->created_at->diffInHours(now()) <= 24 && ! $this->is_flagged;
    }

    /**
     * Check if review can be flagged.
     */
    public function canBeFlagged(): bool
    {
        return ! $this->is_flagged && $this->is_public;
    }

    /**
     * Flag this review for moderation.
     */
    public function flag(string $reason): bool
    {
        if (! $this->canBeFlagged() || ! in_array($reason, self::getValidFlagReasons())) {
            return false;
        }

        $this->is_flagged = true;
        $this->flagged_reason = $reason;
        $this->is_public = false; // Hide flagged reviews

        return $this->save();
    }

    /**
     * Moderate this review (approve or reject).
     */
    public function moderate(int $moderatorId, bool $approve = true): bool
    {
        $this->moderated_by = $moderatorId;
        $this->moderated_at = now();

        if ($approve) {
            $this->is_flagged = false;
            $this->flagged_reason = null;
            $this->is_public = true;
        } else {
            // Keep flagged and hidden
            $this->is_public = false;
        }

        return $this->save();
    }

    /**
     * Calculate weighted rating based on reviewer's credibility.
     */
    public function getWeightedRating(): float
    {
        $reviewerRating = $this->reviewer->average_rating ?? 3.0;
        $reviewerReviewCount = $this->reviewer->total_reviews ?? 0;

        // Base weight is 1.0, increased by reviewer's rating and review count
        $weight = 1.0 + ($reviewerRating - 3.0) * 0.2 + min($reviewerReviewCount / 10, 0.5);

        return $this->rating * $weight;
    }

    /**
     * Calculate rating statistics for a user.
     */
    public static function calculateUserRatingStats(int $userId): array
    {
        $reviews = static::where('reviewee_id', $userId)
            ->where('is_public', true)
            ->get();

        if ($reviews->isEmpty()) {
            return [
                'average_rating' => 0.0,
                'total_reviews' => 0,
                'rating_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                'weighted_average' => 0.0,
            ];
        }

        $totalReviews = $reviews->count();
        $averageRating = $reviews->avg('rating');

        // Calculate weighted average
        $totalWeightedRating = $reviews->sum(function ($review) {
            return $review->getWeightedRating();
        });
        $totalWeight = $reviews->sum(function ($review) {
            $reviewerRating = $review->reviewer->average_rating ?? 3.0;
            $reviewerReviewCount = $review->reviewer->total_reviews ?? 0;

            return 1.0 + ($reviewerRating - 3.0) * 0.2 + min($reviewerReviewCount / 10, 0.5);
        });
        $weightedAverage = $totalWeight > 0 ? $totalWeightedRating / $totalWeight : 0.0;

        // Calculate rating distribution
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($reviews as $review) {
            $distribution[$review->rating]++;
        }

        return [
            'average_rating' => round($averageRating, 2),
            'total_reviews' => $totalReviews,
            'rating_distribution' => $distribution,
            'weighted_average' => round($weightedAverage, 2),
        ];
    }

    /**
     * Scope to filter public reviews.
     * @param mixed $query
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to filter flagged reviews.
     * @param mixed $query
     */
    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }

    /**
     * Scope to filter reviews by rating.
     * @param mixed $query
     */
    public function scopeWithRating($query, int $rating)
    {
        return $query->where('rating', $rating);
    }

    /**
     * Scope to filter positive reviews.
     * @param mixed $query
     */
    public function scopePositive($query)
    {
        return $query->where('rating', '>=', 4);
    }

    /**
     * Scope to filter negative reviews.
     * @param mixed $query
     */
    public function scopeNegative($query)
    {
        return $query->where('rating', '<=', 2);
    }

    /**
     * Scope to filter reviews for a specific user.
     * @param mixed $query
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('reviewee_id', $userId);
    }

    /**
     * Scope to filter reviews by a specific user.
     * @param mixed $query
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('reviewer_id', $userId);
    }

    /**
     * Scope to filter recent reviews.
     * @param mixed $query
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to include relationships.
     * @param mixed $query
     */
    public function scopeWithRelations($query)
    {
        return $query->with([
            'reviewer:id,first_name,last_name,avatar',
            'reviewee:id,first_name,last_name,avatar',
            'job:id,title,status',
        ]);
    }

    /**
     * Scope to order by rating.
     * @param mixed $query
     */
    public function scopeOrderByRating($query, string $direction = 'desc')
    {
        return $query->orderBy('rating', $direction);
    }

    /**
     * Scope to search reviews by comment content.
     * @param mixed $query
     */
    public function scopeSearchComment($query, string $term)
    {
        return $query->where('comment', 'like', "%{$term}%");
    }

    /**
     * Scope to filter reviews that need moderation.
     * @param mixed $query
     */
    public function scopeNeedingModeration($query)
    {
        return $query->where('is_flagged', true)
            ->whereNull('moderated_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_public' => 'boolean',
            'is_flagged' => 'boolean',
            'moderated_at' => 'datetime',
            'response_at' => 'datetime',
        ];
    }
}
