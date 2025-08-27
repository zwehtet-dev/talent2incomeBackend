<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Category::query();

            // Filter by parent category if specified
            if ($request->has('parent_id')) {
                $query->where('parent_id', $request->input('parent_id'));
            }

            // Include subcategories if requested
            if ($request->boolean('include_subcategories')) {
                $query->with('subcategories');
            }

            // Include parent category if requested
            if ($request->boolean('include_parent')) {
                $query->with('parent');
            }

            $categories = $query->orderBy('name')->get();

            return response()->json([
                'data' => $categories,
                'meta' => [
                    'total' => $categories->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve categories',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): JsonResponse
    {
        try {
            $category->load(['subcategories', 'parent']);

            return response()->json([
                'data' => $category,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Category not found',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 404);
        }
    }

    /**
     * Get categories with their skill counts.
     */
    public function withSkillCounts(): JsonResponse
    {
        try {
            $categories = Category::withCount('skills')
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => $categories,
                'meta' => [
                    'total' => $categories->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve categories with skill counts',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get categories with their job counts.
     */
    public function withJobCounts(): JsonResponse
    {
        try {
            $categories = Category::withCount('jobs')
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => $categories,
                'meta' => [
                    'total' => $categories->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve categories with job counts',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
