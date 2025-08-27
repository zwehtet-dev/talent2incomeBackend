<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\FilterBuilder;
use App\Services\SearchService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class SavedSearch extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'filters',
        'sort_options',
        'notifications_enabled',
        'last_notification_sent',
        'notification_frequency',
        'is_active',
    ];

    /**
     * The user who owns this saved search.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Execute the saved search and return results.
     */
    public function execute(int $perPage = 15, int $page = 1)
    {
        $searchService = new SearchService();

        $params = array_merge($this->filters, [
            'per_page' => $perPage,
            'page' => $page,
        ]);

        if ($this->sort_options) {
            $params = array_merge($params, $this->sort_options);
        }

        if ($this->type === 'jobs') {
            return $searchService->searchJobs($params);
        } else {
            return $searchService->searchSkills($params);
        }
    }

    /**
     * Get new results since last notification.
     */
    public function getNewResults()
    {
        $cacheKey = "saved_search_new_results_{$this->id}";

        return Cache::remember($cacheKey, 300, function () {
            $lastCheck = $this->last_notification_sent ?? $this->created_at;

            $filterBuilder = $this->type === 'jobs'
                ? FilterBuilder::forJobs()
                : FilterBuilder::forSkills();

            // Apply saved filters
            $this->applyFiltersToBuilder($filterBuilder);

            // Only get items created since last check
            $filterBuilder->getQuery()->where('created_at', '>', $lastCheck);

            return $filterBuilder->getQuery()->get();
        });
    }

    /**
     * Check if notifications should be sent.
     */
    public function shouldSendNotification(): bool
    {
        if (! $this->notifications_enabled || ! $this->is_active) {
            return false;
        }

        if (! $this->last_notification_sent) {
            return true;
        }

        $hoursSinceLastNotification = $this->last_notification_sent->diffInHours(now());

        return $hoursSinceLastNotification >= $this->notification_frequency;
    }

    /**
     * Mark notification as sent.
     */
    public function markNotificationSent(): void
    {
        $this->update(['last_notification_sent' => now()]);
    }

    /**
     * Get the search URL for this saved search.
     */
    public function getSearchUrl(): string
    {
        $baseUrl = $this->type === 'jobs' ? '/jobs' : '/skills';
        $queryParams = http_build_query($this->filters);

        return $baseUrl . ($queryParams ? '?' . $queryParams : '');
    }

    /**
     * Get a human-readable description of the search criteria.
     */
    public function getDescriptionAttribute(): string
    {
        $parts = [];

        if (! empty($this->filters['query'])) {
            $parts[] = "searching for \"{$this->filters['query']}\"";
        }

        if (! empty($this->filters['category_id'])) {
            $category = Category::find($this->filters['category_id']);
            if ($category) {
                $parts[] = "in {$category->name}";
            }
        }

        if (! empty($this->filters['location'])) {
            $parts[] = "near {$this->filters['location']}";
        }

        if (! empty($this->filters['budget_min']) || ! empty($this->filters['budget_max'])) {
            $min = $this->filters['budget_min'] ?? 0;
            $max = $this->filters['budget_max'] ?? '∞';
            $parts[] = "budget $min - $max";
        }

        if (! empty($this->filters['min_rating'])) {
            $parts[] = "rating {$this->filters['min_rating']}+ stars";
        }

        return ucfirst(implode(', ', $parts)) ?: 'All ' . $this->type;
    }

    /**
     * Scope to filter active saved searches.
     * @param mixed $query
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter saved searches with notifications enabled.
     * @param mixed $query
     */
    public function scopeWithNotifications($query)
    {
        return $query->where('notifications_enabled', true);
    }

    /**
     * Scope to filter saved searches that need notification checks.
     * @param mixed $query
     */
    public function scopeNeedingNotificationCheck($query)
    {
        return $query->active()
            ->withNotifications()
            ->where(function ($q) {
                $q->whereNull('last_notification_sent')
                    ->orWhereRaw('last_notification_sent <= DATE_SUB(NOW(), INTERVAL notification_frequency HOUR)');
            });
    }

    /**
     * Scope to filter by type.
     * @param mixed $query
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'sort_options' => 'array',
            'notifications_enabled' => 'boolean',
            'last_notification_sent' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Apply saved filters to a filter builder.
     */
    private function applyFiltersToBuilder(FilterBuilder $filterBuilder): void
    {
        $filters = $this->filters;

        if (! empty($filters['query'])) {
            $filterBuilder->search($filters['query']);
        }

        if (! empty($filters['category_id'])) {
            $filterBuilder->category((int) $filters['category_id']);
        }

        if (! empty($filters['category_ids'])) {
            $filterBuilder->categories($filters['category_ids']);
        }

        if (! empty($filters['budget_min']) || ! empty($filters['budget_max'])) {
            $filterBuilder->priceRange(
                $filters['budget_min'] ?? null,
                $filters['budget_max'] ?? null,
                $filters['currency'] ?? 'USD'
            );
        }

        if (! empty($filters['location'])) {
            $filterBuilder->location(
                $filters['location'],
                $filters['radius'] ?? null,
                $filters['lat'] ?? null,
                $filters['lng'] ?? null
            );
        }

        if (! empty($filters['min_rating'])) {
            $filterBuilder->minRating((float) $filters['min_rating']);
        }

        if (! empty($filters['urgent'])) {
            $filterBuilder->urgent((bool) $filters['urgent']);
        }

        if (! empty($filters['pricing_type'])) {
            $filterBuilder->pricingType($filters['pricing_type']);
        }

        if (! empty($filters['deadline'])) {
            $filterBuilder->deadline($filters['deadline']);
        }

        if (! empty($filters['availability'])) {
            $filterBuilder->availability((bool) $filters['availability']);
        }

        // Apply sorting
        if ($this->sort_options) {
            $filterBuilder->sortBy(
                $this->sort_options['sort_by'] ?? 'relevance',
                $this->sort_options['direction'] ?? 'desc'
            );
        }
    }
}
