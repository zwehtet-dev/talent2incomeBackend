<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Requests\User\UploadAvatarRequest;
use App\Models\User;
use App\Services\OnlineStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class UserController extends Controller
{
    public function __construct(
        private OnlineStatusService $onlineStatusService
    ) {
    }
    /**
     * Get current user's profile with privacy controls.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'bio' => $user->bio,
            'location' => $user->location,
            'phone' => $user->phone,
            'average_rating' => round($user->average_rating, 1),
            'total_reviews' => $user->total_reviews,
            'jobs_completed' => $user->jobs_completed,
            'skills_offered' => $user->skills_offered,
            'created_at' => $user->created_at,
            'email_verified_at' => $user->email_verified_at,
        ]);
    }

    /**
     * Update current user's profile with validation.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update($request->validated());

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'bio' => $user->bio,
                'location' => $user->location,
                'phone' => $user->phone,
                'average_rating' => round($user->average_rating, 1),
                'total_reviews' => $user->total_reviews,
                'jobs_completed' => $user->jobs_completed,
                'skills_offered' => $user->skills_offered,
            ],
        ]);
    }

    /**
     * Search users with privacy filtering.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string|min:2|max:100',
            'location' => 'nullable|string|max:255',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'has_skills' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = User::query()
            ->active()
            ->where('email_verified_at', '!=', null);

        // Search by name or email (only for verified, active users)
        if ($request->filled('q')) {
            $query->search($request->q);
        }

        // Filter by location
        if ($request->filled('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        // Filter by minimum rating
        if ($request->filled('min_rating')) {
            $query->whereHas('receivedReviews', function ($q) use ($request) {
                $q->havingRaw('AVG(rating) >= ?', [$request->min_rating]);
            });
        }

        // Filter users who have active skills
        if ($request->boolean('has_skills')) {
            $query->whereHas('skills', function ($q) {
                $q->where('is_active', true);
            });
        }

        $perPage = min($request->get('per_page', 15), 50);
        $users = $query->paginate($perPage);

        return response()->json([
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'avatar' => $user->avatar,
                    'bio' => $user->bio,
                    'location' => $user->location,
                    'average_rating' => round($user->average_rating, 1),
                    'total_reviews' => $user->total_reviews,
                    'jobs_completed' => $user->jobs_completed,
                    'skills_offered' => $user->skills_offered,
                    'created_at' => $user->created_at,
                ];
            }),
            'meta' => [
                'current_page' => $users->currentPage(),
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
        ]);
    }

    /**
     * Upload and process user avatar.
     */
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $file = $request->file('avatar');
        $filename = 'avatars/' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();

        // Process and resize image
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getPathname())
            ->cover(300, 300)
            ->toJpeg(85);

        // Store the processed image
        Storage::disk('public')->put($filename, $image);

        // Update user avatar path
        $user->update(['avatar' => $filename]);

        return response()->json([
            'message' => 'Avatar uploaded successfully',
            'avatar_url' => Storage::disk('public')->url($filename),
        ]);
    }

    /**
     * Get user statistics for dashboard.
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get recent activity counts
        $recentJobs = $user->jobs()->where('created_at', '>=', now()->subDays(30))->count();
        $recentSkills = $user->skills()->where('created_at', '>=', now()->subDays(30))->count();
        $recentMessages = $user->sentMessages()->where('created_at', '>=', now()->subDays(7))->count();

        // Get earnings this month (payments received)
        $monthlyEarnings = $user->paymentsReceived()
            ->where('status', 'released')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');

        // Get spending this month (payments made)
        $monthlySpending = $user->paymentsMade()
            ->where('status', 'released')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');

        // Get job status breakdown
        $jobStats = [
            'open' => $user->jobs()->where('status', 'open')->count(),
            'in_progress' => $user->jobs()->where('status', 'in_progress')->count(),
            'completed' => $user->jobs()->where('status', 'completed')->count(),
            'cancelled' => $user->jobs()->where('status', 'cancelled')->count(),
        ];

        // Get recent reviews
        $recentReviews = $user->receivedReviews()
            ->with(['reviewer:id,first_name,last_name', 'job:id,title'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($review) {
                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'reviewer' => [
                        'first_name' => $review->reviewer->first_name,
                        'last_name' => substr($review->reviewer->last_name, 0, 1) . '.',
                    ],
                    'job_title' => $review->job->title,
                    'created_at' => $review->created_at,
                ];
            });

        return response()->json([
            'profile_stats' => [
                'average_rating' => round($user->average_rating, 1),
                'total_reviews' => $user->total_reviews,
                'jobs_completed' => $user->jobs_completed,
                'skills_offered' => $user->skills_offered,
            ],
            'activity_stats' => [
                'recent_jobs_posted' => $recentJobs,
                'recent_skills_added' => $recentSkills,
                'recent_messages_sent' => $recentMessages,
            ],
            'financial_stats' => [
                'monthly_earnings' => round($monthlyEarnings, 2),
                'monthly_spending' => round($monthlySpending, 2),
                'net_monthly' => round($monthlyEarnings - $monthlySpending, 2),
            ],
            'job_stats' => $jobStats,
            'recent_reviews' => $recentReviews,
        ]);
    }

    /**
     * Get public profile of a specific user.
     */
    public function show(User $user): JsonResponse
    {
        // Only show active, verified users
        if (! $user->is_active || ! $user->email_verified_at) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'avatar' => $user->avatar,
            'bio' => $user->bio,
            'location' => $user->location,
            'average_rating' => round($user->average_rating, 1),
            'total_reviews' => $user->total_reviews,
            'jobs_completed' => $user->jobs_completed,
            'skills_offered' => $user->skills_offered,
            'created_at' => $user->created_at,
        ]);
    }

    /**
     * Get online users list.
     */
    public function onlineUsers(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        try {
            $onlineUserIds = $this->onlineStatusService->getOnlineUsers();
            $perPage = min($request->get('per_page', 20), 50);
            $currentPage = $request->get('page', 1);

            // Get user details for online users
            $onlineUsers = User::whereIn('id', $onlineUserIds)
                ->active()
                ->select('id', 'first_name', 'last_name', 'avatar', 'location')
                ->paginate($perPage);

            return response()->json([
                'data' => $onlineUsers->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'avatar' => $user->avatar,
                        'location' => $user->location,
                        'is_online' => true,
                        'last_seen' => $this->onlineStatusService->getUserLastSeen($user->id),
                    ];
                }),
                'meta' => [
                    'current_page' => $onlineUsers->currentPage(),
                    'total' => $onlineUsers->total(),
                    'per_page' => $onlineUsers->perPage(),
                    'last_page' => $onlineUsers->lastPage(),
                ],
                'total_online' => count($onlineUserIds),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve online users.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get specific user's online status.
     */
    public function onlineStatus(User $user): JsonResponse
    {
        $this->authorize('viewOnlineStatus', $user);

        try {
            $isOnline = $this->onlineStatusService->isUserOnline($user->id);
            $lastSeen = $this->onlineStatusService->getUserLastSeen($user->id);

            return response()->json([
                'user_id' => $user->id,
                'is_online' => $isOnline,
                'last_seen' => $lastSeen,
                'last_seen_human' => $lastSeen ? now()->createFromTimestamp($lastSeen)->diffForHumans() : null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve user online status.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
