<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Job;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SearchService
{
    /**
     * Search jobs with advanced filtering and ranking.
     */
    public function searchJobs(array $params): LengthAwarePaginator
    {
        $query = $params['query'] ?? '';
        $categoryId = $params['category_id'] ?? null;
        $budgetMin = $params['budget_min'] ?? null;
        $budgetMax = $params['budget_max'] ?? null;
        $location = $params['location'] ?? null;
        $isUrgent = $params['is_urgent'] ?? null;
        $sortBy = $params['sort_by'] ?? 'relevance';
        $perPage = min($params['per_page'] ?? 15, 50);
        $page = $params['page'] ?? 1;

        // Track search analytics
        $this->trackSearch('jobs', $query, $params);

        // Start with Scout search if query is provided and not using database driver
        if (! empty($query) && config('scout.driver') !== 'database') {
            $jobs = Job::search($query)
                ->query(function (Builder $builder) use ($categoryId, $budgetMin, $budgetMax, $location, $isUrgent) {
                    return $this->applyJobFilters($builder, $categoryId, $budgetMin, $budgetMax, $location, $isUrgent);
                });
        } else {
            // Use regular Eloquent query for filtering and search
            $jobs = Job::query()
                ->where('status', Job::STATUS_OPEN);

            // Apply text search if query provided
            if (! empty($query)) {
                $jobs = $jobs->search($query);
            }

            $jobs = $this->applyJobFilters($jobs, $categoryId, $budgetMin, $budgetMax, $location, $isUrgent);
        }

        // Apply sorting
        $jobs = $this->applySorting($jobs, $sortBy, $query);

        // Get paginated results
        $results = $jobs->paginate($perPage, ['*'], 'page', $page);

        // Load relationships for the results
        $results->getCollection()->load(['user:id,first_name,last_name,avatar,location,rating_cache_average', 'category:id,name,slug']);

        return $results;
    }

    /**
     * Search skills with advanced filtering and ranking.
     */
    public function searchSkills(array $params): LengthAwarePaginator
    {
        $query = $params['query'] ?? '';
        $categoryId = $params['category_id'] ?? null;
        $priceMin = $params['price_min'] ?? null;
        $priceMax = $params['price_max'] ?? null;
        $location = $params['location'] ?? null;
        $pricingType = $params['pricing_type'] ?? null;
        $minRating = $params['min_rating'] ?? null;
        $sortBy = $params['sort_by'] ?? 'relevance';
        $perPage = min($params['per_page'] ?? 15, 50);
        $page = $params['page'] ?? 1;

        // Track search analytics
        $this->trackSearch('skills', $query, $params);

        // Start with Scout search if query is provided and not using database driver
        if (! empty($query) && config('scout.driver') !== 'database') {
            $skills = Skill::search($query)
                ->query(function (Builder $builder) use ($categoryId, $priceMin, $priceMax, $location, $pricingType, $minRating) {
                    return $this->applySkillFilters($builder, $categoryId, $priceMin, $priceMax, $location, $pricingType, $minRating);
                });
        } else {
            // Use regular Eloquent query for filtering and search
            $skills = Skill::query()
                ->where('is_active', true)
                ->where('is_available', true);

            // Apply text search if query provided
            if (! empty($query)) {
                $skills = $skills->search($query);
            }

            $skills = $this->applySkillFilters($skills, $categoryId, $priceMin, $priceMax, $location, $pricingType, $minRating);
        }

        // Apply sorting
        $skills = $this->applySorting($skills, $sortBy, $query);

        // Get paginated results
        $results = $skills->paginate($perPage, ['*'], 'page', $page);

        // Load relationships for the results
        $results->getCollection()->load(['user:id,first_name,last_name,avatar,location,rating_cache_average', 'category:id,name,slug']);

        return $results;
    }

    /**
     * Get search suggestions for autocomplete.
     */
    public function getSearchSuggestions(string $query, string $type = 'both', int $limit = 10): array
    {
        $cacheKey = "search_suggestions_{$type}_" . md5($query) . "_{$limit}";

        return Cache::remember($cacheKey, 300, function () use ($query, $type, $limit) {
            $suggestions = [];

            if ($type === 'jobs' || $type === 'both') {
                // Get job title suggestions
                $jobTitles = Job::where('status', Job::STATUS_OPEN)
                    ->where('title', 'like', "%{$query}%")
                    ->select('title')
                    ->distinct()
                    ->limit($limit)
                    ->pluck('title')
                    ->toArray();

                // Get category suggestions for jobs
                $jobCategories = Job::where('status', Job::STATUS_OPEN)
                    ->whereHas('category', function ($q) use ($query) {
                        $q->where('name', 'like', "%{$query}%");
                    })
                    ->with('category:id,name')
                    ->limit($limit)
                    ->get()
                    ->pluck('category.name')
                    ->unique()
                    ->values()
                    ->toArray();

                $suggestions['jobs'] = [
                    'titles' => $jobTitles,
                    'categories' => $jobCategories,
                    'combined' => array_unique(array_merge($jobTitles, $jobCategories)),
                ];
            }

            if ($type === 'skills' || $type === 'both') {
                // Get skill title suggestions
                $skillTitles = Skill::where('is_active', true)
                    ->where('is_available', true)
                    ->where('title', 'like', "%{$query}%")
                    ->select('title')
                    ->distinct()
                    ->limit($limit)
                    ->pluck('title')
                    ->toArray();

                // Get category suggestions for skills
                $skillCategories = Skill::where('is_active', true)
                    ->where('is_available', true)
                    ->whereHas('category', function ($q) use ($query) {
                        $q->where('name', 'like', "%{$query}%");
                    })
                    ->with('category:id,name')
                    ->limit($limit)
                    ->get()
                    ->pluck('category.name')
                    ->unique()
                    ->values()
                    ->toArray();

                $suggestions['skills'] = [
                    'titles' => $skillTitles,
                    'categories' => $skillCategories,
                    'combined' => array_unique(array_merge($skillTitles, $skillCategories)),
                ];
            }

            // Add popular search terms that match the query
            $popularMatches = $this->getPopularSearchMatches($query, $limit);
            if (! empty($popularMatches)) {
                $suggestions['popular'] = $popularMatches;
            }

            return $suggestions;
        });
    }

    /**
     * Get popular search terms.
     */
    public function getPopularSearches(int $limit = 10): array
    {
        return Cache::remember('popular_searches', 3600, function () use ($limit) {
            return DB::table('search_analytics')
                ->select('query', DB::raw('COUNT(*) as count'))
                ->where('query', '!=', '')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('query')
                ->orderByDesc('count')
                ->limit($limit)
                ->pluck('count', 'query')
                ->toArray();
        });
    }

    /**
     * Get search analytics data.
     */
    public function getSearchAnalytics(int $days = 30): array
    {
        $cacheKey = "search_analytics_{$days}";

        return Cache::remember($cacheKey, 1800, function () use ($days) {
            $startDate = now()->subDays($days);

            return [
                'total_searches' => DB::table('search_analytics')
                    ->where('created_at', '>=', $startDate)
                    ->count(),
                'unique_queries' => DB::table('search_analytics')
                    ->where('created_at', '>=', $startDate)
                    ->distinct('query')
                    ->count(),
                'searches_by_type' => DB::table('search_analytics')
                    ->select('type', DB::raw('COUNT(*) as count'))
                    ->where('created_at', '>=', $startDate)
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray(),
                'top_queries' => DB::table('search_analytics')
                    ->select('query', DB::raw('COUNT(*) as count'))
                    ->where('query', '!=', '')
                    ->where('created_at', '>=', $startDate)
                    ->groupBy('query')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->pluck('count', 'query')
                    ->toArray(),
                'searches_by_day' => DB::table('search_analytics')
                    ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                    ->where('created_at', '>=', $startDate)
                    ->groupBy(DB::raw('DATE(created_at)'))
                    ->orderBy('date')
                    ->pluck('count', 'date')
                    ->toArray(),
            ];
        });
    }

    /**
     * Get trending search terms.
     */
    public function getTrendingSearches(string $type = 'both', int $limit = 10, int $days = 7): array
    {
        $cacheKey = "trending_searches_{$type}_{$limit}_{$days}";

        return Cache::remember($cacheKey, 1800, function () use ($type, $limit, $days) {
            $query = DB::table('search_analytics')
                ->select('query', DB::raw('COUNT(*) as count'))
                ->where('query', '!=', '')
                ->where('created_at', '>=', now()->subDays($days));

            if ($type !== 'both') {
                $query->where('type', $type);
            }

            return $query->groupBy('query')
                ->orderByDesc('count')
                ->limit($limit)
                ->pluck('count', 'query')
                ->toArray();
        });
    }

    /**
     * Get search facets for filtering.
     */
    public function getSearchFacets(string $type, string $query = ''): array
    {
        $cacheKey = "search_facets_{$type}_" . md5($query);

        return Cache::remember($cacheKey, 900, function () use ($type, $query) {
            if ($type === 'jobs') {
                return $this->getJobFacets($query);
            } else {
                return $this->getSkillFacets($query);
            }
        });
    }

    /**
     * Get popular search terms that match the query.
     */
    private function getPopularSearchMatches(string $query, int $limit): array
    {
        return DB::table('search_analytics')
            ->select('query', DB::raw('COUNT(*) as count'))
            ->where('query', 'like', "%{$query}%")
            ->where('query', '!=', '')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('query')
            ->orderByDesc('count')
            ->limit($limit)
            ->pluck('query')
            ->toArray();
    }

    /**
     * Apply job-specific filters.
     */
    private function applyJobFilters(Builder $query, mixed $categoryId, mixed $budgetMin, mixed $budgetMax, ?string $location, mixed $isUrgent): Builder
    {
        if ($categoryId) {
            $query->where('category_id', (int) $categoryId);
        }

        if ($budgetMin !== null || $budgetMax !== null) {
            $query->inBudgetRange(
                $budgetMin !== null ? (float) $budgetMin : null,
                $budgetMax !== null ? (float) $budgetMax : null
            );
        }

        if ($location) {
            $query->whereHas('user', function ($q) use ($location) {
                $q->where('location', 'like', "%{$location}%");
            });
        }

        if ($isUrgent !== null) {
            $query->where('is_urgent', (bool) $isUrgent);
        }

        return $query;
    }

    /**
     * Apply skill-specific filters.
     */
    private function applySkillFilters(Builder $query, mixed $categoryId, mixed $priceMin, mixed $priceMax, ?string $location, ?string $pricingType, mixed $minRating): Builder
    {
        if ($categoryId) {
            $query->where('category_id', (int) $categoryId);
        }

        if ($priceMin !== null || $priceMax !== null) {
            $query->inPriceRange(
                $priceMin !== null ? (float) $priceMin : null,
                $priceMax !== null ? (float) $priceMax : null
            );
        }

        if ($location) {
            $query->whereHas('user', function ($q) use ($location) {
                $q->where('location', 'like', "%{$location}%");
            });
        }

        if ($pricingType) {
            $query->where('pricing_type', $pricingType);
        }

        if ($minRating !== null) {
            $query->whereHas('user', function ($q) use ($minRating) {
                $q->where('rating_cache_average', '>=', (float) $minRating);
            });
        }

        return $query;
    }

    /**
     * Apply sorting to the query.
     * @param mixed $query
     */
    private function applySorting($query, string $sortBy, string $searchQuery = ''): mixed
    {
        return match ($sortBy) {
            'relevance' => $this->applyRelevanceSorting($query, $searchQuery),
            'newest' => $query->latest(),
            'oldest' => $query->oldest(),
            'budget_high' => $query instanceof Builder ? $query->orderByBudget('desc') : $query,
            'budget_low' => $query instanceof Builder ? $query->orderByBudget('asc') : $query,
            'price_high' => $query instanceof Builder ? $query->orderByRaw('COALESCE(price_fixed, price_per_hour, 0) DESC') : $query,
            'price_low' => $query instanceof Builder ? $query->orderByRaw('COALESCE(price_fixed, price_per_hour, 0) ASC') : $query,
            'rating' => $query instanceof Builder ? $query->orderByUserRating('desc') : $query,
            'deadline' => $query instanceof Builder ? $query->orderByDeadline('asc') : $query,
            default => $query->latest(),
        };
    }

    /**
     * Apply relevance-based sorting.
     * @param mixed $query
     */
    private function applyRelevanceSorting($query, string $searchQuery): mixed
    {
        if (empty($searchQuery)) {
            return $query->latest();
        }

        // For Scout queries, relevance is handled by the search engine
        if (! ($query instanceof Builder)) {
            return $query;
        }

        // For database queries with search, just use the default ordering
        // The search scope already handles the filtering
        return $query->latest();
    }

    /**
     * Get job search facets.
     */
    private function getJobFacets(string $query): array
    {
        $baseQuery = Job::where('status', Job::STATUS_OPEN);

        if (! empty($query)) {
            $baseQuery = $baseQuery->search($query);
        }

        return [
            'categories' => $baseQuery->clone()
                ->join('categories', 'job_postings.category_id', '=', 'categories.id')
                ->groupBy('categories.id', 'categories.name')
                ->selectRaw('categories.id, categories.name, COUNT(*) as count')
                ->orderByDesc('count')
                ->limit(20)
                ->get()
                ->toArray(),
            'budget_ranges' => [
                ['range' => '0-100', 'label' => 'Under $100', 'count' => $this->getJobBudgetCount($baseQuery, 0, 100)],
                ['range' => '100-500', 'label' => '$100 - $500', 'count' => $this->getJobBudgetCount($baseQuery, 100, 500)],
                ['range' => '500-1000', 'label' => '$500 - $1,000', 'count' => $this->getJobBudgetCount($baseQuery, 500, 1000)],
                ['range' => '1000-5000', 'label' => '$1,000 - $5,000', 'count' => $this->getJobBudgetCount($baseQuery, 1000, 5000)],
                ['range' => '5000+', 'label' => '$5,000+', 'count' => $this->getJobBudgetCount($baseQuery, 5000, null)],
            ],
            'urgency' => [
                ['value' => true, 'label' => 'Urgent', 'count' => $baseQuery->clone()->where('is_urgent', true)->count()],
                ['value' => false, 'label' => 'Regular', 'count' => $baseQuery->clone()->where('is_urgent', false)->count()],
            ],
            'locations' => $baseQuery->clone()
                ->join('users', 'job_postings.user_id', '=', 'users.id')
                ->whereNotNull('users.location')
                ->groupBy('users.location')
                ->selectRaw('users.location, COUNT(*) as count')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'location')
                ->toArray(),
        ];
    }

    /**
     * Get skill search facets.
     */
    private function getSkillFacets(string $query): array
    {
        $baseQuery = Skill::where('is_active', true)->where('is_available', true);

        if (! empty($query)) {
            $baseQuery = $baseQuery->search($query);
        }

        return [
            'categories' => $baseQuery->clone()
                ->join('categories', 'skills.category_id', '=', 'categories.id')
                ->groupBy('categories.id', 'categories.name')
                ->selectRaw('categories.id, categories.name, COUNT(*) as count')
                ->orderByDesc('count')
                ->limit(20)
                ->get()
                ->toArray(),
            'pricing_types' => [
                ['value' => 'hourly', 'label' => 'Hourly', 'count' => $baseQuery->clone()->where('pricing_type', 'hourly')->count()],
                ['value' => 'fixed', 'label' => 'Fixed Price', 'count' => $baseQuery->clone()->where('pricing_type', 'fixed')->count()],
                ['value' => 'negotiable', 'label' => 'Negotiable', 'count' => $baseQuery->clone()->where('pricing_type', 'negotiable')->count()],
            ],
            'price_ranges' => [
                ['range' => '0-25', 'label' => 'Under $25', 'count' => $this->getSkillPriceCount($baseQuery, 0, 25)],
                ['range' => '25-50', 'label' => '$25 - $50', 'count' => $this->getSkillPriceCount($baseQuery, 25, 50)],
                ['range' => '50-100', 'label' => '$50 - $100', 'count' => $this->getSkillPriceCount($baseQuery, 50, 100)],
                ['range' => '100-200', 'label' => '$100 - $200', 'count' => $this->getSkillPriceCount($baseQuery, 100, 200)],
                ['range' => '200+', 'label' => '$200+', 'count' => $this->getSkillPriceCount($baseQuery, 200, null)],
            ],
            'ratings' => [
                ['range' => '4.5+', 'label' => '4.5+ Stars', 'count' => $this->getSkillRatingCount($baseQuery, 4.5, 5)],
                ['range' => '4.0+', 'label' => '4.0+ Stars', 'count' => $this->getSkillRatingCount($baseQuery, 4.0, 5)],
                ['range' => '3.5+', 'label' => '3.5+ Stars', 'count' => $this->getSkillRatingCount($baseQuery, 3.5, 5)],
                ['range' => '3.0+', 'label' => '3.0+ Stars', 'count' => $this->getSkillRatingCount($baseQuery, 3.0, 5)],
            ],
            'locations' => $baseQuery->clone()
                ->join('users', 'skills.user_id', '=', 'users.id')
                ->whereNotNull('users.location')
                ->groupBy('users.location')
                ->selectRaw('users.location, COUNT(*) as count')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'location')
                ->toArray(),
        ];
    }

    /**
     * Get job count for budget range.
     * @param mixed $query
     */
    private function getJobBudgetCount($query, ?float $min, ?float $max): int
    {
        $budgetQuery = $query->clone();

        if ($min !== null && $max !== null) {
            $budgetQuery->inBudgetRange($min, $max);
        } elseif ($min !== null) {
            $budgetQuery->inBudgetRange($min, null);
        }

        return $budgetQuery->count();
    }

    /**
     * Get skill count for price range.
     * @param mixed $query
     */
    private function getSkillPriceCount($query, ?float $min, ?float $max): int
    {
        $priceQuery = $query->clone();

        if ($min !== null && $max !== null) {
            $priceQuery->inPriceRange($min, $max);
        } elseif ($min !== null) {
            $priceQuery->inPriceRange($min, null);
        }

        return $priceQuery->count();
    }

    /**
     * Get skill count for rating range.
     * @param mixed $query
     */
    private function getSkillRatingCount($query, float $min, float $max): int
    {
        return $query->clone()
            ->join('users', 'skills.user_id', '=', 'users.id')
            ->whereBetween('users.rating_cache_average', [$min, $max])
            ->count();
    }

    /**
     * Track search analytics.
     */
    private function trackSearch(string $type, string $query, array $params): void
    {
        try {
            // Only track if we have a request context (not in tests/console)
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return;
            }

            DB::table('search_analytics')->insert([
                'type' => $type,
                'query' => $query,
                'filters' => json_encode($params),
                'user_id' => auth()->id(),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to track search analytics', [
                'error' => $e->getMessage(),
                'type' => $type,
                'query' => $query,
            ]);
        }
    }
}
