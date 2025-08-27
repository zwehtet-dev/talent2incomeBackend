<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CacheService;
use App\Services\FilterBuilder;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SearchController extends Controller
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly CacheService $cacheService
    ) {
    }

    /**
     * Search jobs with advanced filtering.
     */
    public function searchJobs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0|gte:budget_min',
            'location' => 'nullable|string|max:255',
            'is_urgent' => 'nullable|boolean',
            'sort_by' => ['nullable', 'string', Rule::in([
                'relevance', 'newest', 'oldest', 'budget_high', 'budget_low', 'deadline',
            ])],
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
        ]);

        return $this->cacheService->cacheSearchResults(
            $validated['query'] ?? '',
            $validated,
            function () use ($validated) {
                $results = $this->searchService->searchJobs($validated);

                return response()->json([
                    'data' => $results->items(),
                    'meta' => [
                        'current_page' => $results->currentPage(),
                        'from' => $results->firstItem(),
                        'last_page' => $results->lastPage(),
                        'per_page' => $results->perPage(),
                        'to' => $results->lastItem(),
                        'total' => $results->total(),
                    ],
                ]);
            },
            CacheService::SHORT_TTL
        );
    }

    /**
     * Search skills with advanced filtering.
     */
    public function searchSkills(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0|gte:price_min',
            'location' => 'nullable|string|max:255',
            'pricing_type' => ['nullable', 'string', Rule::in(['hourly', 'fixed', 'negotiable'])],
            'min_rating' => 'nullable|numeric|min:1|max:5',
            'sort_by' => ['nullable', 'string', Rule::in([
                'relevance', 'newest', 'oldest', 'price_high', 'price_low', 'rating',
            ])],
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
        ]);

        $results = $this->searchService->searchSkills($validated);

        return response()->json([
            'data' => $results->items(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'from' => $results->firstItem(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'to' => $results->lastItem(),
                'total' => $results->total(),
            ],
        ]);
    }

    /**
     * Get search suggestions for autocomplete.
     */
    // public function suggestions(Request $request): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'query' => 'required|string|min:2|max:255',
    //         'type' => ['nullable', 'string', Rule::in(['jobs', 'skills', 'both'])],
    //         'limit' => 'nullable|integer|min:1|max:20',
    //     ]);

    //     $suggestions = $this->searchService->getSearchSuggestions(
    //         $validated['query'],
    //         $validated['type'] ?? 'both',
    //         $validated['limit'] ?? 10
    //     );

    //     return response()->json([
    //         'data' => $suggestions,
    //         'meta' => [
    //             'query' => $validated['query'],
    //             'type' => $validated['type'] ?? 'both',
    //             'cached' => true, // Suggestions are cached for performance
    //         ],
    //     ]);
    // }

    /**
     * Get trending search terms.
     */
    public function trending(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(['jobs', 'skills', 'both'])],
            'limit' => 'nullable|integer|min:1|max:20',
            'days' => 'nullable|integer|min:1|max:30',
        ]);

        $trending = $this->searchService->getTrendingSearches(
            $validated['type'] ?? 'both',
            $validated['limit'] ?? 10,
            $validated['days'] ?? 7
        );

        return response()->json([
            'data' => $trending,
        ]);
    }

    /**
     * Get search facets for filtering.
     */
    public function facets(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:255',
            'type' => ['required', 'string', Rule::in(['jobs', 'skills'])],
        ]);

        $facets = $this->searchService->getSearchFacets(
            $validated['type'],
            $validated['query'] ?? ''
        );

        return response()->json([
            'data' => $facets,
        ]);
    }

    /**
     * Get popular search terms.
     */
    public function popularSearches(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $popularSearches = $this->searchService->getPopularSearches($validated['limit'] ?? 10);

        return response()->json([
            'data' => $popularSearches,
        ]);
    }

    /**
     * Get search analytics (admin only).
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            if (! auth()->user()->is_admin) {
                abort(403, 'Access denied. Admin privileges required.');
            }

            $validated = $request->validate([
                'days' => 'nullable|integer|min:1|max:365',
            ]);

            $analytics = $this->searchService->getSearchAnalytics($validated['days'] ?? 30);

            return response()->json([
                'data' => $analytics,
            ]);
        } catch (\Exception $e) {
            \Log::error('Search analytics error: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Combined search across jobs and skills.
     */
    public function searchAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'location' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:25',
        ]);

        $perPage = min($validated['per_page'] ?? 10, 25);

        // Search both jobs and skills
        $jobResults = $this->searchService->searchJobs([
            ...$validated,
            'per_page' => $perPage,
            'sort_by' => 'relevance',
        ]);

        $skillResults = $this->searchService->searchSkills([
            ...$validated,
            'per_page' => $perPage,
            'sort_by' => 'relevance',
        ]);

        return response()->json([
            'data' => [
                'jobs' => [
                    'data' => $jobResults->items(),
                    'total' => $jobResults->total(),
                    'has_more' => $jobResults->hasMorePages(),
                ],
                'skills' => [
                    'data' => $skillResults->items(),
                    'total' => $skillResults->total(),
                    'has_more' => $skillResults->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * Advanced job filtering with FilterBuilder.
     */
    public function advancedJobSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:255',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0|gte:budget_min',
            'currency' => 'nullable|string|size:3',
            'location' => 'nullable|string|max:255',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:500',
            'urgent' => 'nullable|boolean',
            'deadline' => ['nullable', 'string', Rule::in(['today', 'week', 'month', '3months'])],
            'min_rating' => 'nullable|numeric|min:1|max:5',
            'sort_by' => ['nullable', 'string', Rule::in([
                'relevance', 'newest', 'oldest', 'price', 'rating', 'deadline', 'distance',
            ])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $filterBuilder = FilterBuilder::forJobs();

        // Apply filters
        if (! empty($validated['query'])) {
            $filterBuilder->search($validated['query']);
        }

        if (! empty($validated['category_ids'])) {
            $filterBuilder->categories($validated['category_ids']);
        }

        if (! empty($validated['budget_min']) || ! empty($validated['budget_max'])) {
            $filterBuilder->priceRange(
                $validated['budget_min'] ?? null,
                $validated['budget_max'] ?? null,
                $validated['currency'] ?? 'USD'
            );
        }

        if (! empty($validated['location'])) {
            $filterBuilder->location(
                $validated['location'],
                $validated['radius'] ?? null,
                $validated['lat'] ?? null,
                $validated['lng'] ?? null
            );
        }

        if (isset($validated['urgent'])) {
            $filterBuilder->urgent($validated['urgent']);
        }

        if (! empty($validated['deadline'])) {
            $filterBuilder->deadline($validated['deadline']);
        }

        if (! empty($validated['min_rating'])) {
            $filterBuilder->minRating($validated['min_rating']);
        }

        $filterBuilder->availability(true);

        // Apply sorting
        $filterBuilder->sortBy(
            $validated['sort_by'] ?? 'relevance',
            $validated['sort_direction'] ?? 'desc'
        );

        // Get results
        $results = $filterBuilder->paginate(
            $validated['per_page'] ?? 15,
            $validated['page'] ?? 1
        );

        return response()->json([
            'data' => $results->items(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'from' => $results->firstItem(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'to' => $results->lastItem(),
                'total' => $results->total(),
            ],
            'filters' => [
                'applied' => $filterBuilder->getAppliedFilters(),
                'available' => $filterBuilder->getAvailableFilters(),
            ],
        ]);
    }

    /**
     * Advanced skill filtering with FilterBuilder.
     */
    public function advancedSkillSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:255',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0|gte:price_min',
            'currency' => 'nullable|string|size:3',
            'location' => 'nullable|string|max:255',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:500',
            'pricing_type' => ['nullable', 'string', Rule::in(['hourly', 'fixed', 'negotiable'])],
            'min_rating' => 'nullable|numeric|min:1|max:5',
            'sort_by' => ['nullable', 'string', Rule::in([
                'relevance', 'newest', 'oldest', 'price', 'rating', 'distance',
            ])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $filterBuilder = FilterBuilder::forSkills();

        // Apply filters
        if (! empty($validated['query'])) {
            $filterBuilder->search($validated['query']);
        }

        if (! empty($validated['category_ids'])) {
            $filterBuilder->categories($validated['category_ids']);
        }

        if (! empty($validated['price_min']) || ! empty($validated['price_max'])) {
            $filterBuilder->priceRange(
                $validated['price_min'] ?? null,
                $validated['price_max'] ?? null,
                $validated['currency'] ?? 'USD'
            );
        }

        if (! empty($validated['location'])) {
            $filterBuilder->location(
                $validated['location'],
                $validated['radius'] ?? null,
                $validated['lat'] ?? null,
                $validated['lng'] ?? null
            );
        }

        if (! empty($validated['pricing_type'])) {
            $filterBuilder->pricingType($validated['pricing_type']);
        }

        if (! empty($validated['min_rating'])) {
            $filterBuilder->minRating($validated['min_rating']);
        }

        $filterBuilder->availability(true);

        // Apply sorting
        $filterBuilder->sortBy(
            $validated['sort_by'] ?? 'relevance',
            $validated['sort_direction'] ?? 'desc'
        );

        // Get results
        $results = $filterBuilder->paginate(
            $validated['per_page'] ?? 15,
            $validated['page'] ?? 1
        );

        return response()->json([
            'data' => $results->items(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'from' => $results->firstItem(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'to' => $results->lastItem(),
                'total' => $results->total(),
            ],
            'filters' => [
                'applied' => $filterBuilder->getAppliedFilters(),
                'available' => $filterBuilder->getAvailableFilters(),
            ],
        ]);
    }

    /**
     * Get search suggestions based on query.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:1|max:255',
            'type' => 'nullable|string|in:jobs,skills,all',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        try {
            $query = $validated['query'];
            $type = $validated['type'] ?? 'all';
            $limit = $validated['limit'] ?? 10;

            $suggestions = [];

            // Simple suggestions based on existing data
            if ($type === 'jobs' || $type === 'all') {
                $jobSuggestions = \App\Models\Job::where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->limit($limit)
                    ->pluck('title')
                    ->unique()
                    ->values();
                $suggestions['jobs'] = $jobSuggestions;
            }

            if ($type === 'skills' || $type === 'all') {
                $skillSuggestions = \App\Models\Skill::where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->limit($limit)
                    ->pluck('name')
                    ->unique()
                    ->values();
                $suggestions['skills'] = $skillSuggestions;
            }

            return response()->json([
                'data' => $suggestions,
                'query' => $query,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get search suggestions',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
