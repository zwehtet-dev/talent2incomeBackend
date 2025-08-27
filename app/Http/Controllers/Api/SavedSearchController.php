<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SavedSearchController extends Controller
{
    /**
     * Display a listing of the user's saved searches.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', Rule::in(['jobs', 'skills'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Auth::user()->savedSearches()->latest();

        if ($request->filled('type')) {
            $query->ofType($request->type);
        }

        $savedSearches = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $savedSearches->items(),
            'meta' => [
                'current_page' => $savedSearches->currentPage(),
                'total' => $savedSearches->total(),
                'per_page' => $savedSearches->perPage(),
                'last_page' => $savedSearches->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created saved search.
     */
    public function store(Request $request): JsonResponse
    {
        // Check if user has reached the limit (e.g., 20 saved searches)
        $savedSearchCount = Auth::user()->savedSearches()->count();
        if ($savedSearchCount >= 20) {
            return response()->json([
                'message' => 'You have reached the maximum number of saved searches.',
                'errors' => ['limit' => ['Maximum of 20 saved searches allowed.']],
            ], 422);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['jobs', 'skills'])],
            'filters' => ['required', 'array'],
            'sort_options' => ['nullable', 'array'],
            'notifications_enabled' => ['nullable', 'boolean'],
            'notification_frequency' => ['nullable', 'integer', 'min:1', 'max:168'], // max 1 week
        ]);

        // Check if user already has a saved search with this name
        $existingSearch = Auth::user()->savedSearches()
            ->where('name', $request->name)
            ->where('type', $request->type)
            ->first();

        if ($existingSearch) {
            return response()->json([
                'message' => 'You already have a saved search with this name for this type.',
                'errors' => ['name' => ['A saved search with this name already exists.']],
            ], 422);
        }

        $savedSearch = Auth::user()->savedSearches()->create([
            'name' => $request->name,
            'type' => $request->type,
            'filters' => $request->filters,
            'sort_options' => $request->sort_options,
            'notifications_enabled' => $request->get('notifications_enabled', true),
            'notification_frequency' => $request->get('notification_frequency', 24),
        ]);

        return response()->json([
            'message' => 'Saved search created successfully.',
            'data' => $savedSearch,
        ], 201);
    }

    /**
     * Display the specified saved search.
     */
    public function show(SavedSearch $savedSearch): JsonResponse
    {
        if (Auth::user()->id !== $savedSearch->user_id) {
            abort(403, 'Unauthorized');
        }

        return response()->json([
            'data' => $savedSearch,
        ]);
    }

    /**
     * Execute a saved search and return results.
     */
    public function execute(Request $request, SavedSearch $savedSearch): JsonResponse
    {
        if (Auth::user()->id !== $savedSearch->user_id) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $results = $savedSearch->execute(
            $request->get('per_page', 15),
            $request->get('page', 1)
        );

        return response()->json([
            'data' => $results->items(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'last_page' => $results->lastPage(),
            ],
            'search_info' => [
                'name' => $savedSearch->name,
                'description' => $savedSearch->description,
                'filters' => $savedSearch->filters,
                'sort_options' => $savedSearch->sort_options,
            ],
        ]);
    }

    /**
     * Get new results for a saved search since last notification.
     */
    public function newResults(SavedSearch $savedSearch): JsonResponse
    {
        if (Auth::user()->id !== $savedSearch->user_id) {
            abort(403, 'Unauthorized');
        }

        $newResults = $savedSearch->getNewResults();

        return response()->json([
            'data' => $newResults,
            'count' => $newResults->count(),
            'last_check' => $savedSearch->last_notification_sent ?? $savedSearch->created_at,
        ]);
    }

    /**
     * Update the specified saved search.
     */
    public function update(Request $request, SavedSearch $savedSearch): JsonResponse
    {
        if (Auth::user()->id !== $savedSearch->user_id) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'filters' => ['sometimes', 'array'],
            'sort_options' => ['nullable', 'array'],
            'notifications_enabled' => ['nullable', 'boolean'],
            'notification_frequency' => ['nullable', 'integer', 'min:1', 'max:168'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Check for duplicate name if name is being updated
        if ($request->filled('name') && $request->name !== $savedSearch->name) {
            $existingSearch = Auth::user()->savedSearches()
                ->where('name', $request->name)
                ->where('type', $savedSearch->type)
                ->where('id', '!=', $savedSearch->id)
                ->first();

            if ($existingSearch) {
                return response()->json([
                    'message' => 'You already have a saved search with this name for this type.',
                    'errors' => ['name' => ['A saved search with this name already exists.']],
                ], 422);
            }
        }

        $savedSearch->update($request->only([
            'name',
            'filters',
            'sort_options',
            'notifications_enabled',
            'notification_frequency',
            'is_active',
        ]));

        return response()->json([
            'message' => 'Saved search updated successfully.',
            'data' => $savedSearch->fresh(),
        ]);
    }

    /**
     * Remove the specified saved search.
     */
    public function destroy(SavedSearch $savedSearch): JsonResponse
    {
        if (Auth::user()->id !== $savedSearch->user_id) {
            abort(403, 'Unauthorized');
        }

        $savedSearch->delete();

        return response()->json([
            'message' => 'Saved search deleted successfully.',
        ]);
    }

    /**
     * Toggle notifications for a saved search.
     */
    public function toggleNotifications(SavedSearch $savedSearch): JsonResponse
    {
        if (Auth::user()->id !== $savedSearch->user_id) {
            abort(403, 'Unauthorized');
        }

        $savedSearch->update([
            'notifications_enabled' => ! $savedSearch->notifications_enabled,
        ]);

        return response()->json([
            'message' => $savedSearch->notifications_enabled
                ? 'Notifications enabled for this saved search.'
                : 'Notifications disabled for this saved search.',
            'data' => $savedSearch->fresh(),
        ]);
    }

    /**
     * Get available filter options for building searches.
     */
    public function filterOptions(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', Rule::in(['jobs', 'skills'])],
        ]);

        $filterBuilder = $request->type === 'jobs'
            ? \App\Services\FilterBuilder::forJobs()
            : \App\Services\FilterBuilder::forSkills();

        $options = $filterBuilder->getAvailableFilters();

        return response()->json([
            'data' => $options,
        ]);
    }
}
