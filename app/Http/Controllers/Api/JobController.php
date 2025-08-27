<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Job\CreateJobRequest;
use App\Http\Requests\Job\JobSearchRequest;
use App\Http\Requests\Job\UpdateJobRequest;
use App\Models\Job;
use App\Services\CacheService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Log;

class JobController extends Controller
{
    protected CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }
    /**
     * Display a listing of jobs with filtering and search.
     */
    public function index(JobSearchRequest $request): JsonResponse
    {
        $params = $request->getValidatedWithDefaults();

        // Use search cache for search queries
        if (! empty($params['search'])) {
            return $this->cacheService->cacheSearchResults(
                $params['search'],
                $params,
                function () use ($params) {
                    return $this->executeJobQuery($params);
                }
            );
        }

        // Use query cache for filtered results
        $cacheKey = 'jobs:list:' . md5(serialize($params));

        return $this->cacheService->cacheQuery(
            $cacheKey,
            function () use ($params) {
                return $this->executeJobQuery($params);
            },
            CacheService::SHORT_TTL,
            ['jobs']
        );
    }

    /**
     * Store a newly created job.
     */
    public function store(CreateJobRequest $request): JsonResponse
    {
        try {

            $this->authorize('create', Job::class);

            $validated = $request->validated();
            $validated['user_id'] = Auth::id();

            $job = Job::create($validated);
            $job->load(['user', 'category']);

            return response()->json([
                'message' => 'Job created successfully.',
                'data' => $job,
            ], 201);
        } catch (\Exception $e) {
            // Log full exception with stack trace
            Log::error('Job creation failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return only safe details to client
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified job.
     */
    public function show(Job $job): JsonResponse
    {
        $this->authorize('view', $job);

        $userId = Auth::id();

        return $this->cacheService->cacheJobData(
            $job->id,
            'details:' . ($userId ?? 'guest'),
            function () use ($job, $userId) {
                $job->load([
                    'user:id,first_name,last_name,avatar,location',
                    'category:id,name,slug,description',
                    'assignedUser:id,first_name,last_name,avatar',
                    'reviews' => function ($query) {
                        $query->with('reviewer:id,first_name,last_name')
                            ->latest()
                            ->limit(5);
                    },
                ]);

                // Add computed attributes
                $job->append([
                    'budget_display',
                    'average_budget',
                    'is_expired',
                    'days_until_deadline',
                    'is_near_deadline',
                ]);

                // Add application count if user is job owner
                if ($userId === $job->user_id) {
                    $job->applications_count = $job->messages()
                        ->where('content', 'like', '%application%')
                        ->distinct('sender_id')
                        ->count();
                }

                return response()->json([
                    'data' => $job,
                ]);
            },
            CacheService::MEDIUM_TTL
        );
    }

    /**
     * Update the specified job.
     */
    public function update(UpdateJobRequest $request, Job $job): JsonResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validated();

        // Handle status transitions with business logic
        if (isset($validated['status'])) {
            $this->handleStatusTransition($job, $validated['status'], $validated);
        }

        $job->update($validated);
        $job->load(['user', 'category', 'assignedUser']);

        return response()->json([
            'message' => 'Job updated successfully.',
            'data' => $job,
        ]);
    }

    /**
     * Remove the specified job (soft delete).
     */
    public function destroy(Job $job): JsonResponse
    {
        $this->authorize('delete', $job);

        // Check if job can be deleted
        if ($job->status === Job::STATUS_IN_PROGRESS) {
            return response()->json([
                'message' => 'Cannot delete job that is in progress.',
            ], 422);
        }

        if ($job->payment && $job->payment->status === 'held') {
            return response()->json([
                'message' => 'Cannot delete job with held payment.',
            ], 422);
        }

        DB::transaction(function () use ($job) {
            // Soft delete related data
            $job->messages()->delete();
            $job->delete();
        });

        return response()->json([
            'message' => 'Job deleted successfully.',
        ]);
    }

    /**
     * Search jobs with advanced filtering.
     */
    public function search(JobSearchRequest $request): JsonResponse
    {
        // This method is essentially the same as index but with a different endpoint
        // for semantic clarity in the API
        return $this->index($request);
    }

    /**
     * Get jobs posted by the authenticated user.
     */
    public function myJobs(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 50);
        $status = $request->get('status');

        $query = Job::where('user_id', Auth::id())
            ->withRelations();

        if ($status && in_array($status, Job::getValidStatuses())) {
            $query->where('status', $status);
        }

        $jobs = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $jobs->items(),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'total' => $jobs->total(),
                'per_page' => $jobs->perPage(),
                'last_page' => $jobs->lastPage(),
            ],
        ]);
    }

    /**
     * Get jobs assigned to the authenticated user.
     */
    public function assignedJobs(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 50);
        $status = $request->get('status');

        $query = Job::where('assigned_to', Auth::id())
            ->withRelations();

        if ($status && in_array($status, Job::getValidStatuses())) {
            $query->where('status', $status);
        }

        $jobs = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $jobs->items(),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'total' => $jobs->total(),
                'per_page' => $jobs->perPage(),
                'last_page' => $jobs->lastPage(),
            ],
        ]);
    }

    /**
     * Execute the job query with filters and pagination
     */
    private function executeJobQuery(array $params): JsonResponse
    {
        $query = Job::query()->withRelations();

        // Apply search
        if (! empty($params['search'])) {
            $query->search($params['search']);

            if ($params['sort_by'] === 'relevance') {
                $query->orderByRelevance($params['search']);
            }
        }

        // Apply filters
        $this->applyFilters($query, $params);

        // Apply sorting (if not relevance)
        if ($params['sort_by'] !== 'relevance') {
            $this->applySorting($query, $params['sort_by'], $params['sort_direction']);
        }

        // Paginate results
        $jobs = $query->paginate(
            perPage: $params['per_page'],
            page: $params['page']
        );

        return response()->json([
            'data' => $jobs->items(),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'total' => $jobs->total(),
                'per_page' => $jobs->perPage(),
                'last_page' => $jobs->lastPage(),
                'from' => $jobs->firstItem(),
                'to' => $jobs->lastItem(),
            ],
            'links' => [
                'first' => $jobs->url(1),
                'last' => $jobs->url($jobs->lastPage()),
                'prev' => $jobs->previousPageUrl(),
                'next' => $jobs->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Apply filters to the query.
     * @param mixed $query
     */
    private function applyFilters($query, array $params): void
    {
        if (! empty($params['category_id'])) {
            $query->inCategory($params['category_id']);
        }

        if (! empty($params['status'])) {
            $query->withStatus($params['status']);
        }

        if (isset($params['is_urgent']) && $params['is_urgent']) {
            $query->urgent();
        }

        if (! empty($params['budget_min']) || ! empty($params['budget_max'])) {
            $query->inBudgetRange($params['budget_min'] ?? null, $params['budget_max'] ?? null);
        }

        if (! empty($params['budget_type'])) {
            $query->where('budget_type', $params['budget_type']);
        }

        if (! empty($params['deadline_from']) || ! empty($params['deadline_to'])) {
            $query->whereBetween('deadline', [
                $params['deadline_from'] ?? '1970-01-01',
                $params['deadline_to'] ?? '2099-12-31',
            ]);
        }

        if (! empty($params['location'])) {
            $query->whereHas('user', function ($q) use ($params) {
                $q->where('location', 'like', '%' . $params['location'] . '%');
            });
        }
    }

    /**
     * Apply sorting to the query.
     * @param mixed $query
     */
    private function applySorting($query, string $sortBy, string $direction): void
    {
        switch ($sortBy) {
            case 'deadline':
                $query->orderByDeadline($direction);

                break;
            case 'budget':
                $query->orderByBudget($direction);

                break;
            case 'created_at':
            default:
                $query->orderBy('created_at', $direction);

                break;
        }
    }

    /**
     * Handle status transitions with business logic.
     */
    private function handleStatusTransition(Job $job, string $newStatus, array &$validated): void
    {
        switch ($newStatus) {
            case Job::STATUS_IN_PROGRESS:
                // Ensure job is assigned when moving to in progress
                if (! $job->assigned_to && ! isset($validated['assigned_to'])) {
                    throw new \InvalidArgumentException('Job must be assigned before moving to in progress.');
                }

                break;

            case Job::STATUS_COMPLETED:
                // Job must be in progress to be completed
                if ($job->status !== Job::STATUS_IN_PROGRESS) {
                    throw new \InvalidArgumentException('Only jobs in progress can be marked as completed.');
                }

                break;

            case Job::STATUS_CANCELLED:
                // Clear assignment when cancelling
                $validated['assigned_to'] = null;

                break;

            case Job::STATUS_OPEN:
                // When reopening, clear assignment
                if (in_array($job->status, [Job::STATUS_CANCELLED, Job::STATUS_EXPIRED])) {
                    $validated['assigned_to'] = null;
                }

                break;
        }
    }
}
