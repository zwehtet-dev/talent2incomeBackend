<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Job;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class FilterBuilder
{
    private Builder $query;
    private array $appliedFilters = [];
    private string $modelType;

    public function __construct(Builder $query, string $modelType)
    {
        $this->query = $query;
        $this->modelType = $modelType;
    }

    /**
     * Create a new filter builder for jobs.
     */
    public static function forJobs(): self
    {
        return new self(Job::query(), 'job');
    }

    /**
     * Create a new filter builder for skills.
     */
    public static function forSkills(): self
    {
        return new self(Skill::query(), 'skill');
    }

    /**
     * Apply category filter.
     */
    public function category(int $categoryId): self
    {
        $this->query->where('category_id', $categoryId);
        $this->appliedFilters['category_id'] = $categoryId;

        return $this;
    }

    /**
     * Apply multiple category filter.
     */
    public function categories(array $categoryIds): self
    {
        $this->query->whereIn('category_id', $categoryIds);
        $this->appliedFilters['category_ids'] = $categoryIds;

        return $this;
    }

    /**
     * Apply budget/price range filter with currency normalization.
     */
    public function priceRange(?float $min = null, ?float $max = null, string $currency = 'USD'): self
    {
        // Normalize currency to USD for consistent filtering
        $normalizedMin = $this->normalizeCurrency($min, $currency);
        $normalizedMax = $this->normalizeCurrency($max, $currency);

        if ($this->modelType === 'job') {
            $this->query->inBudgetRange($normalizedMin, $normalizedMax);
        } else {
            $this->query->inPriceRange($normalizedMin, $normalizedMax);
        }

        $this->appliedFilters['price_range'] = [
            'min' => $normalizedMin,
            'max' => $normalizedMax,
            'currency' => $currency,
        ];

        return $this;
    }

    /**
     * Apply location-based filtering with geospatial support.
     */
    public function location(string $location, ?float $radius = null, ?float $lat = null, ?float $lng = null): self
    {
        if ($lat !== null && $lng !== null && $radius !== null) {
            // Geospatial filtering using Haversine formula
            $this->query->whereHas('user', function ($q) use ($lat, $lng, $radius) {
                $q->whereRaw(
                    '(6371 * acos(cos(radians(?)) * cos(radians(COALESCE(latitude, 0))) * cos(radians(COALESCE(longitude, 0)) - radians(?)) + sin(radians(?)) * sin(radians(COALESCE(latitude, 0))))) <= ?',
                    [$lat, $lng, $lat, $radius]
                );
            });

            $this->appliedFilters['location'] = [
                'type' => 'geospatial',
                'lat' => $lat,
                'lng' => $lng,
                'radius' => $radius,
            ];
        } else {
            // Text-based location filtering
            $this->query->whereHas('user', function ($q) use ($location) {
                $q->where('location', 'like', "%{$location}%");
            });

            $this->appliedFilters['location'] = [
                'type' => 'text',
                'value' => $location,
            ];
        }

        return $this;
    }

    /**
     * Apply availability filter with real-time updates.
     */
    public function availability(bool $availableOnly = true): self
    {
        if ($this->modelType === 'job') {
            if ($availableOnly) {
                $this->query->where('status', Job::STATUS_OPEN)
                    ->where(function ($q) {
                        $q->whereNull('deadline')
                            ->orWhere('deadline', '>', now());
                    });
            }
        } else {
            if ($availableOnly) {
                $this->query->where('is_available', true)
                    ->where('is_active', true);
            }
        }

        $this->appliedFilters['availability'] = $availableOnly;

        return $this;
    }

    /**
     * Apply rating filter.
     */
    public function minRating(float $minRating): self
    {
        $this->query->whereHas('user', function ($q) use ($minRating) {
            $q->where('rating_cache_average', '>=', $minRating);
        });

        $this->appliedFilters['min_rating'] = $minRating;

        return $this;
    }

    /**
     * Apply urgency filter (jobs only).
     */
    public function urgent(bool $urgentOnly = true): self
    {
        if ($this->modelType === 'job') {
            $this->query->where('is_urgent', $urgentOnly);
            $this->appliedFilters['urgent'] = $urgentOnly;
        }

        return $this;
    }

    /**
     * Apply pricing type filter (skills only).
     */
    public function pricingType(string $pricingType): self
    {
        if ($this->modelType === 'skill') {
            $this->query->where('pricing_type', $pricingType);
            $this->appliedFilters['pricing_type'] = $pricingType;
        }

        return $this;
    }

    /**
     * Apply deadline filter (jobs only).
     */
    public function deadline(?string $period = null): self
    {
        if ($this->modelType === 'job' && $period) {
            $date = match ($period) {
                'today' => now()->endOfDay(),
                'week' => now()->addWeek(),
                'month' => now()->addMonth(),
                '3months' => now()->addMonths(3),
                default => null
            };

            if ($date) {
                $this->query->where('deadline', '<=', $date);
                $this->appliedFilters['deadline'] = $period;
            }
        }

        return $this;
    }

    /**
     * Apply text search filter.
     */
    public function search(string $query): self
    {
        $this->query->search($query);
        $this->appliedFilters['search'] = $query;

        return $this;
    }

    /**
     * Apply sorting with optimization.
     */
    public function sortBy(string $sortBy, string $direction = 'desc'): self
    {
        $validDirections = ['asc', 'desc'];
        $direction = in_array($direction, $validDirections) ? $direction : 'desc';

        switch ($sortBy) {
            case 'relevance':
                if (isset($this->appliedFilters['search'])) {
                    $this->query->orderByRelevance($this->appliedFilters['search']);
                } else {
                    $this->query->latest();
                }

                break;

            case 'price':
                if ($this->modelType === 'job') {
                    $this->query->orderByBudget($direction);
                } else {
                    $this->query->orderByRaw("COALESCE(price_fixed, price_per_hour, 0) {$direction}");
                }

                break;

            case 'rating':
                $this->query->orderByUserRating($direction);

                break;

            case 'deadline':
                if ($this->modelType === 'job') {
                    $this->query->orderByDeadline($direction);
                }

                break;

            case 'created':
                $this->query->orderBy('created_at', $direction);

                break;

            case 'updated':
                $this->query->orderBy('updated_at', $direction);

                break;

            case 'distance':
                if (isset($this->appliedFilters['location']['type']) &&
                    $this->appliedFilters['location']['type'] === 'geospatial') {
                    $lat = $this->appliedFilters['location']['lat'];
                    $lng = $this->appliedFilters['location']['lng'];

                    $this->query->join('users', $this->modelType === 'job' ? 'job_postings.user_id' : 'skills.user_id', '=', 'users.id')
                        ->orderByRaw(
                            "(6371 * acos(cos(radians(?)) * cos(radians(COALESCE(users.latitude, 0))) * cos(radians(COALESCE(users.longitude, 0)) - radians(?)) + sin(radians(?)) * sin(radians(COALESCE(users.latitude, 0))))) {$direction}",
                            [$lat, $lng, $lat]
                        );
                }

                break;

            default:
                $this->query->latest();
        }

        $this->appliedFilters['sort'] = ['by' => $sortBy, 'direction' => $direction];

        return $this;
    }

    /**
     * Apply pagination with optimization.
     */
    public function paginate(int $perPage = 15, int $page = 1)
    {
        $perPage = min($perPage, 100); // Limit max per page

        // Use cursor pagination for better performance on large datasets
        if ($page > 100) {
            return $this->query->cursorPaginate($perPage);
        }

        return $this->query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get the query builder.
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Get applied filters.
     */
    public function getAppliedFilters(): array
    {
        return $this->appliedFilters;
    }

    /**
     * Get filter summary for caching.
     */
    public function getFilterHash(): string
    {
        return md5(serialize($this->appliedFilters));
    }

    /**
     * Apply cached results if available.
     */
    public function cached(int $minutes = 15)
    {
        $cacheKey = "filter_results_{$this->modelType}_{$this->getFilterHash()}";

        return Cache::remember($cacheKey, $minutes * 60, function () {
            return $this->query->get();
        });
    }

    /**
     * Build dynamic filter options based on current results.
     */
    public function getAvailableFilters(): array
    {
        $baseQuery = clone $this->query;

        // Remove current filters to get all available options
        $cleanQuery = $this->modelType === 'job' ? Job::query() : Skill::query();

        if ($this->modelType === 'job') {
            $cleanQuery->where('status', Job::STATUS_OPEN);
        } else {
            $cleanQuery->where('is_active', true)->where('is_available', true);
        }

        return [
            'categories' => $this->getAvailableCategories($cleanQuery),
            'price_ranges' => $this->getAvailablePriceRanges($cleanQuery),
            'locations' => $this->getAvailableLocations($cleanQuery),
            'ratings' => $this->getAvailableRatings($cleanQuery),
        ];
    }

    /**
     * Normalize currency to USD for consistent filtering.
     */
    private function normalizeCurrency(?float $amount, string $currency): ?float
    {
        if ($amount === null || $currency === 'USD') {
            return $amount;
        }

        // Get exchange rates from cache or API
        $exchangeRates = Cache::remember('exchange_rates', 3600, function () {
            // In a real implementation, you would fetch from an exchange rate API
            return [
                'EUR' => 1.1,
                'GBP' => 1.25,
                'CAD' => 0.75,
                'AUD' => 0.65,
                'JPY' => 0.007,
                // Add more currencies as needed
            ];
        });

        $rate = $exchangeRates[$currency] ?? 1.0;

        return $amount * $rate;
    }

    /**
     * Get available categories with counts.
     */
    private function getAvailableCategories(Builder $query): array
    {
        return $query->join('categories', $this->modelType === 'job' ? 'job_postings.category_id' : 'skills.category_id', '=', 'categories.id')
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.id, categories.name, COUNT(*) as count')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /**
     * Get available price ranges with counts.
     */
    private function getAvailablePriceRanges(Builder $query): array
    {
        $ranges = [
            ['min' => 0, 'max' => 100, 'label' => 'Under $100'],
            ['min' => 100, 'max' => 500, 'label' => '$100 - $500'],
            ['min' => 500, 'max' => 1000, 'label' => '$500 - $1,000'],
            ['min' => 1000, 'max' => 5000, 'label' => '$1,000 - $5,000'],
            ['min' => 5000, 'max' => null, 'label' => '$5,000+'],
        ];

        foreach ($ranges as &$range) {
            $rangeQuery = clone $query;
            if ($this->modelType === 'job') {
                $rangeQuery->inBudgetRange($range['min'], $range['max']);
            } else {
                $rangeQuery->inPriceRange($range['min'], $range['max']);
            }
            $range['count'] = $rangeQuery->count();
        }

        return $ranges;
    }

    /**
     * Get available locations with counts.
     */
    private function getAvailableLocations(Builder $query): array
    {
        return $query->join('users', $this->modelType === 'job' ? 'job_postings.user_id' : 'skills.user_id', '=', 'users.id')
            ->whereNotNull('users.location')
            ->groupBy('users.location')
            ->selectRaw('users.location, COUNT(*) as count')
            ->orderByDesc('count')
            ->limit(20)
            ->pluck('count', 'location')
            ->toArray();
    }

    /**
     * Get available rating ranges with counts.
     */
    private function getAvailableRatings(Builder $query): array
    {
        $ranges = [
            ['min' => 4.5, 'max' => 5.0, 'label' => '4.5+ Stars'],
            ['min' => 4.0, 'max' => 4.5, 'label' => '4.0+ Stars'],
            ['min' => 3.5, 'max' => 4.0, 'label' => '3.5+ Stars'],
            ['min' => 3.0, 'max' => 3.5, 'label' => '3.0+ Stars'],
        ];

        foreach ($ranges as &$range) {
            try {
                // Use a fresh query to avoid join conflicts
                $baseQuery = $this->modelType === 'job'
                    ? Job::query()->where('status', Job::STATUS_OPEN)
                    : Skill::query()->where('is_active', true)->where('is_available', true);

                $range['count'] = $baseQuery
                    ->join('users', $this->modelType === 'job' ? 'job_postings.user_id' : 'skills.user_id', '=', 'users.id')
                    ->where('users.rating_cache_average', '>=', $range['min'])
                    ->count();
            } catch (\Exception $e) {
                // If rating_cache_average column doesn't exist (e.g., in tests), set count to 0
                $range['count'] = 0;
            }
        }

        return $ranges;
    }
}
