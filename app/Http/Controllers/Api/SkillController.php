<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Skill\CreateSkillRequest;
use App\Http\Requests\Skill\SkillSearchRequest;
use App\Http\Requests\Skill\UpdateSkillRequest;
use App\Models\Skill;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SkillController extends Controller
{
    /**
     * Display a listing of skills with filtering and search.
     */
    public function index(SkillSearchRequest $request): JsonResponse
    {
        $params = $request->getSearchParams();

        $query = Skill::query()
            ->active()
            ->withRelations();

        // Apply availability filter
        if ($params['is_available']) {
            $query->available();
        }

        // Apply category filter
        if (! empty($params['category_id'])) {
            $query->inCategory($params['category_id']);
        }

        // Apply pricing type filter
        if (! empty($params['pricing_type'])) {
            $query->byPricingType($params['pricing_type']);
        }

        // Apply price range filter
        if (! empty($params['min_price']) || ! empty($params['max_price'])) {
            $query->inPriceRange($params['min_price'] ?? null, $params['max_price'] ?? null);
        }

        // Apply location filter
        if (! empty($params['location'])) {
            $query->nearLocation($params['location']);
        }

        // Apply search term
        if (! empty($params['search'])) {
            $query->search($params['search']);

            // Order by relevance if searching
            if ($params['sort_by'] === 'relevance') {
                $query->orderByRelevance($params['search']);
            }
        }

        // Apply sorting
        if ($params['sort_by'] !== 'relevance' || empty($params['search'])) {
            switch ($params['sort_by']) {
                case 'price':
                    // Sort by minimum price (hourly or fixed)
                    $query->orderByRaw("
                        CASE
                            WHEN pricing_type = 'hourly' THEN price_per_hour
                            WHEN pricing_type = 'fixed' THEN price_fixed
                            ELSE 999999
                        END {$params['sort_direction']}
                    ");

                    break;
                case 'rating':
                    $query->orderByUserRating($params['sort_direction']);

                    break;
                default:
                    $query->orderBy($params['sort_by'], $params['sort_direction']);
            }
        }

        // Paginate results
        $skills = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        return response()->json([
            'data' => $skills->items(),
            'meta' => [
                'current_page' => $skills->currentPage(),
                'total' => $skills->total(),
                'per_page' => $skills->perPage(),
                'last_page' => $skills->lastPage(),
                'from' => $skills->firstItem(),
                'to' => $skills->lastItem(),
            ],
            'filters' => $params,
        ]);
    }

    /**
     * Store a newly created skill.
     */
    public function store(CreateSkillRequest $request): JsonResponse
    {
        $skill = Skill::create([
            'user_id' => $request->user()->id,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'category_id' => $request->input('category_id'),
            'price_per_hour' => $request->input('price_per_hour'),
            'price_fixed' => $request->input('price_fixed'),
            'pricing_type' => $request->input('pricing_type'),
            'is_available' => $request->input('is_available', true),
            'is_active' => true,
        ]);

        $skill->load(['user:id,first_name,last_name,avatar,location', 'category:id,name,slug']);

        return response()->json([
            'message' => 'Skill created successfully.',
            'data' => $skill,
        ], 201);
    }

    /**
     * Display the specified skill.
     */
    public function show(Skill $skill): JsonResponse
    {
        // Check if user can view this skill
        if (! Gate::allows('view', $skill)) {
            return response()->json([
                'message' => 'Skill not found.',
            ], 404);
        }

        $skill->load([
            'user:id,first_name,last_name,avatar,location,created_at',
            'category:id,name,slug,description',
        ]);

        // Load user's average rating and review count
        $skill->user->loadCount('receivedReviews');
        $skill->user->load(['receivedReviews' => function ($query) {
            $query->selectRaw('reviewee_id, AVG(rating) as average_rating')
                ->groupBy('reviewee_id');
        }]);

        // Add computed attributes
        $skillData = $skill->toArray();
        $skillData['display_price'] = $skill->display_price;
        $skillData['min_price'] = $skill->min_price;

        // Add user statistics
        if ($skill->user->receivedReviews->isNotEmpty()) {
            $skillData['user']['average_rating'] = round($skill->user->receivedReviews->first()->average_rating, 1);
        } else {
            $skillData['user']['average_rating'] = null;
        }
        $skillData['user']['total_reviews'] = $skill->user->received_reviews_count;

        return response()->json([
            'data' => $skillData,
        ]);
    }

    /**
     * Update the specified skill.
     */
    public function update(UpdateSkillRequest $request, Skill $skill): JsonResponse
    {
        // Check authorization
        if (! Gate::allows('update', $skill)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $skill->update($request->validated());

        $skill->load(['user:id,first_name,last_name,avatar,location', 'category:id,name,slug']);

        return response()->json([
            'message' => 'Skill updated successfully.',
            'data' => $skill,
        ]);
    }

    /**
     * Remove the specified skill (soft delete).
     */
    public function destroy(Skill $skill): JsonResponse
    {
        // Check authorization
        if (! Gate::allows('delete', $skill)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        // Check for dependencies (active jobs using this skill's category)
        $activeJobsCount = $skill->jobs()
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        if ($activeJobsCount > 0) {
            return response()->json([
                'message' => 'Cannot delete skill. There are active jobs in this category.',
                'active_jobs_count' => $activeJobsCount,
            ], 422);
        }

        $skill->delete();

        return response()->json([
            'message' => 'Skill deleted successfully.',
        ]);
    }

    /**
     * Search skills with advanced filtering.
     */
    public function search(SkillSearchRequest $request): JsonResponse
    {
        // This method provides the same functionality as index but with a dedicated endpoint
        return $this->index($request);
    }

    /**
     * Get skills by category.
     */
    public function byCategory(Request $request, int $categoryId): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'sort_by' => 'sometimes|in:created_at,price,rating',
            'sort_direction' => 'sometimes|in:asc,desc',
        ]);

        $perPage = $request->input('per_page', 15);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $query = Skill::query()
            ->active()
            ->available()
            ->inCategory($categoryId)
            ->withRelations();

        // Apply sorting
        switch ($sortBy) {
            case 'price':
                $query->orderByRaw("
                    CASE
                        WHEN pricing_type = 'hourly' THEN price_per_hour
                        WHEN pricing_type = 'fixed' THEN price_fixed
                        ELSE 999999
                    END {$sortDirection}
                ");

                break;
            case 'rating':
                $query->orderByUserRating($sortDirection);

                break;
            default:
                $query->orderBy($sortBy, $sortDirection);
        }

        $skills = $query->paginate($perPage);

        return response()->json([
            'data' => $skills->items(),
            'meta' => [
                'current_page' => $skills->currentPage(),
                'total' => $skills->total(),
                'per_page' => $skills->perPage(),
                'last_page' => $skills->lastPage(),
                'from' => $skills->firstItem(),
                'to' => $skills->lastItem(),
            ],
            'category_id' => $categoryId,
        ]);
    }

    /**
     * Get user's own skills.
     */
    public function mySkills(Request $request): JsonResponse
    {
        $request->validate([
            'include_inactive' => 'sometimes|boolean',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $query = Skill::query()
            ->where('user_id', $request->user()->id)
            ->withRelations();

        // Include inactive skills if requested
        if (! $request->boolean('include_inactive')) {
            $query->active();
        }

        $skills = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $skills->items(),
            'meta' => [
                'current_page' => $skills->currentPage(),
                'total' => $skills->total(),
                'per_page' => $skills->perPage(),
                'last_page' => $skills->lastPage(),
                'from' => $skills->firstItem(),
                'to' => $skills->lastItem(),
            ],
        ]);
    }

    /**
     * Toggle skill availability.
     */
    public function toggleAvailability(Skill $skill): JsonResponse
    {
        try {
            // Check authorization
            if (! Gate::allows('update', $skill)) {
                return response()->json([
                    'message' => 'This action is unauthorized.',
                ], 403);
            }

            $skill->update([
                'is_available' => ! $skill->is_available,
            ]);

            return response()->json([
                'message' => $skill->is_available
                    ? 'Skill is now available.'
                    : 'Skill is now unavailable.',
                'is_available' => $skill->is_available,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
