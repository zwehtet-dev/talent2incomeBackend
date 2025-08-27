<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Job extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Searchable;
    use \App\Traits\CacheInvalidation;

    /**
     * The possible job statuses.
     */
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'job_postings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'description',
        'budget_min',
        'budget_max',
        'budget_type',
        'deadline',
        'status',
        'assigned_to',
        'is_urgent',
    ];

    /**
     * Get all valid statuses.
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ];
    }

    /**
     * The user who posted this job.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The category this job belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The user assigned to this job.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Messages related to this job.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Payment for this job.
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Reviews for this job.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get the budget display string.
     */
    public function getBudgetDisplayAttribute(): string
    {
        $budgetMin = $this->budget_min ? (float) $this->budget_min : null;
        $budgetMax = $this->budget_max ? (float) $this->budget_max : null;

        if ($budgetMin && $budgetMax) {
            if ($budgetMin == $budgetMax) {
                return '$' . number_format($budgetMin, 2);
            }

            return '$' . number_format($budgetMin, 2) . ' - $' . number_format($budgetMax, 2);
        }

        if ($budgetMin) {
            return 'From $' . number_format($budgetMin, 2);
        }

        if ($budgetMax) {
            return 'Up to $' . number_format($budgetMax, 2);
        }

        return 'Budget negotiable';
    }

    /**
     * Get the average budget.
     */
    public function getAverageBudgetAttribute(): ?float
    {
        $budgetMin = $this->budget_min ? (float) $this->budget_min : null;
        $budgetMax = $this->budget_max ? (float) $this->budget_max : null;

        if ($budgetMin && $budgetMax) {
            return ($budgetMin + $budgetMax) / 2;
        }

        return $budgetMin ?? $budgetMax;
    }

    /**
     * Check if the job is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->deadline && $this->deadline->isPast() && $this->status === self::STATUS_OPEN;
    }

    /**
     * Get days until deadline.
     */
    public function getDaysUntilDeadlineAttribute(): ?int
    {
        if (! $this->deadline) {
            return null;
        }

        return (int) Carbon::now()->diffInDays($this->deadline, false);
    }

    /**
     * Check if job is near deadline (within 3 days).
     */
    public function getIsNearDeadlineAttribute(): bool
    {
        $days = $this->days_until_deadline;

        return $days !== null && $days <= 3 && $days >= 0;
    }

    /**
     * Check if job can be assigned.
     */
    public function canBeAssigned(): bool
    {
        return $this->status === self::STATUS_OPEN && ! $this->is_expired;
    }

    /**
     * Check if job can be started.
     */
    public function canBeStarted(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->assigned_to !== null;
    }

    /**
     * Check if job can be completed.
     */
    public function canBeCompleted(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if job can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Assign job to a user.
     */
    public function assignTo(User $user): bool
    {
        if (! $this->canBeAssigned()) {
            return false;
        }

        $this->assigned_to = $user->id;
        $this->status = self::STATUS_IN_PROGRESS;

        return $this->save();
    }

    /**
     * Mark job as completed.
     */
    public function markAsCompleted(): bool
    {
        if (! $this->canBeCompleted()) {
            return false;
        }

        $this->status = self::STATUS_COMPLETED;

        return $this->save();
    }

    /**
     * Cancel the job.
     */
    public function cancel(): bool
    {
        if (! $this->canBeCancelled()) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        $this->assigned_to = null;

        return $this->save();
    }

    /**
     * Mark job as expired.
     */
    public function markAsExpired(): bool
    {
        if ($this->status !== self::STATUS_OPEN) {
            return false;
        }

        $this->status = self::STATUS_EXPIRED;

        return $this->save();
    }

    /**
     * Scope to filter by status.
     * @param mixed $query
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter open jobs.
     * @param mixed $query
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope to filter in progress jobs.
     * @param mixed $query
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope to filter completed jobs.
     * @param mixed $query
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to filter expired jobs.
     * @param mixed $query
     */
    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED)
            ->orWhere(function ($q) {
                $q->where('status', self::STATUS_OPEN)
                    ->where('deadline', '<', Carbon::now());
            });
    }

    /**
     * Scope to filter urgent jobs.
     * @param mixed $query
     */
    public function scopeUrgent($query)
    {
        return $query->where('is_urgent', true);
    }

    /**
     * Scope to filter by budget range.
     * @param mixed $query
     */
    public function scopeInBudgetRange($query, ?float $minBudget = null, ?float $maxBudget = null)
    {
        return $query->where(function ($q) use ($minBudget, $maxBudget) {
            if ($minBudget !== null && $maxBudget !== null) {
                // Job budget range overlaps with search range
                $q->where(function ($subQ) use ($minBudget, $maxBudget) {
                    $subQ->where(function ($innerQ) use ($minBudget, $maxBudget) {
                        // Job has both min and max, check for overlap
                        $innerQ->whereNotNull('budget_min')
                            ->whereNotNull('budget_max')
                            ->where('budget_min', '<=', $maxBudget)
                            ->where('budget_max', '>=', $minBudget);
                    })->orWhere(function ($innerQ) use ($minBudget, $maxBudget) {
                        // Job has only min, check if it's within range
                        $innerQ->whereNotNull('budget_min')
                            ->whereNull('budget_max')
                            ->where('budget_min', '<=', $maxBudget);
                    })->orWhere(function ($innerQ) use ($minBudget, $maxBudget) {
                        // Job has only max, check if it's within range
                        $innerQ->whereNull('budget_min')
                            ->whereNotNull('budget_max')
                            ->where('budget_max', '>=', $minBudget);
                    });
                });
            } elseif ($minBudget !== null) {
                // Only minimum budget specified
                $q->where(function ($subQ) use ($minBudget) {
                    $subQ->where('budget_max', '>=', $minBudget)
                        ->orWhere(function ($innerQ) use ($minBudget) {
                            $innerQ->whereNull('budget_max')
                                ->where('budget_min', '>=', $minBudget);
                        });
                });
            } elseif ($maxBudget !== null) {
                // Only maximum budget specified
                $q->where(function ($subQ) use ($maxBudget) {
                    $subQ->where('budget_min', '<=', $maxBudget)
                        ->orWhere(function ($innerQ) use ($maxBudget) {
                            $innerQ->whereNull('budget_min')
                                ->where('budget_max', '<=', $maxBudget);
                        });
                });
            }
        });
    }

    /**
     * Scope for full-text search.
     * @param mixed $query
     */
    public function scopeSearch($query, string $term)
    {
        // Use database-agnostic search for compatibility with SQLite in tests
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /**
     * Scope to order by relevance for search.
     * @param mixed $query
     */
    public function scopeOrderByRelevance($query, string $term)
    {
        // For SQLite compatibility, order by title match first, then description match
        return $query->orderByRaw(
            'CASE 
                WHEN title LIKE ? THEN 1 
                WHEN description LIKE ? THEN 2 
                ELSE 3 
            END',
            ["%{$term}%", "%{$term}%"]
        );
    }

    /**
     * Scope to filter by category.
     * @param mixed $query
     */
    public function scopeInCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope to include relationships.
     * @param mixed $query
     */
    public function scopeWithRelations($query)
    {
        return $query->with([
            'user:id,first_name,last_name,avatar,location',
            'category:id,name,slug',
            'assignedUser:id,first_name,last_name,avatar',
        ]);
    }

    /**
     * Scope to order by deadline.
     * @param mixed $query
     */
    public function scopeOrderByDeadline($query, string $direction = 'asc')
    {
        return $query->orderBy('deadline', $direction);
    }

    /**
     * Scope to order by budget.
     * @param mixed $query
     */
    public function scopeOrderByBudget($query, string $direction = 'desc')
    {
        return $query->orderByRaw("COALESCE(budget_max, budget_min, 0) {$direction}");
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        $array = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'budget_min' => $this->budget_min,
            'budget_max' => $this->budget_max,
            'budget_type' => $this->budget_type,
            'deadline' => $this->deadline?->timestamp,
            'status' => $this->status,
            'is_urgent' => $this->is_urgent,
            'category_id' => $this->category_id,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at?->timestamp,
            'updated_at' => $this->updated_at?->timestamp,
        ];

        // Add related data for advanced search engines (Algolia, Meilisearch)
        if (config('scout.driver') !== 'database') {
            $this->loadMissing(['user', 'category']);

            if ($this->user) {
                $array['user'] = [
                    'id' => $this->user->id,
                    'first_name' => $this->user->first_name,
                    'last_name' => $this->user->last_name,
                    'location' => $this->user->location,
                    'rating_cache_average' => $this->user->rating_cache_average ?? 0,
                ];
            }

            if ($this->category) {
                $array['category'] = [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }

            // Add computed fields for better ranking
            $array['average_budget'] = $this->average_budget;
            $array['days_until_deadline'] = $this->days_until_deadline;
            $array['is_near_deadline'] = $this->is_near_deadline;
        }

        return $array;
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status === self::STATUS_OPEN && ! $this->trashed();
    }

    /**
     * Get the Scout search index name.
     */
    public function searchableAs(): string
    {
        return 'jobs_index';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'deadline' => 'date',
            'is_urgent' => 'boolean',
        ];
    }

    /**
     * Modify the query used to retrieve models when making all of the models searchable.
     * @param mixed $query
     */
    protected function makeAllSearchableUsing($query)
    {
        return $query->with(['user:id,first_name,last_name,location', 'category:id,name']);
    }
}
