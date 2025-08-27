<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUserActionRequest;
use App\Http\Requests\Admin\ModerateContentRequest;
use App\Http\Requests\Admin\ResolveDisputeRequest;
use App\Models\Job;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    /**
     * Get admin dashboard with key performance indicators.
     */
    public function dashboard(): JsonResponse
    {
        $this->authorize('admin.dashboard');

        try {
            $cacheKey = 'admin_dashboard_' . now()->format('Y-m-d-H');

            $dashboardData = Cache::remember($cacheKey, 3600, function () {
                return [
                    'overview' => $this->getDashboardOverview(),
                    'recent_activity' => $this->getRecentActivity(),
                    'pending_issues' => $this->getPendingIssues(),
                    'financial_summary' => $this->getFinancialSummary(),
                    'user_metrics' => $this->getUserMetrics(),
                    'platform_health' => $this->getPlatformHealth(),
                ];
            });

            return response()->json([
                'message' => 'Dashboard data retrieved successfully',
                'data' => $dashboardData,
            ]);
        } catch (\Exception $e) {
            Log::error('Admin dashboard error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to retrieve dashboard data',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get user management data with bulk operations support.
     */
    public function users(Request $request): JsonResponse
    {
        $this->authorize('admin.users.manage');

        try {
            $query = User::withTrashed()
                ->with(['jobs', 'skills', 'receivedReviews'])
                ->withCount([
                    'jobs',
                    'skills',
                    'receivedReviews',
                    'paymentsMade',
                    'paymentsReceived',
                ]);

            // Apply filters
            if ($request->filled('search')) {
                $query->search($request->search);
            }

            if ($request->filled('status')) {
                switch ($request->status) {
                    case 'active':
                        $query->active();

                        break;
                    case 'inactive':
                        $query->where('is_active', false);

                        break;
                    case 'deleted':
                        $query->onlyTrashed();

                        break;
                    case 'locked':
                        $query->whereNotNull('locked_until')
                            ->where('locked_until', '>', now());

                        break;
                }
            }

            if ($request->filled('role')) {
                if ($request->role === 'admin') {
                    $query->admins();
                } else {
                    $query->where('is_admin', false);
                }
            }

            if ($request->filled('registration_date')) {
                $date = Carbon::parse($request->registration_date);
                $query->whereDate('created_at', $date);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            $allowedSorts = [
                'created_at', 'last_login_at', 'last_activity_at',
                'cached_average_rating', 'cached_total_reviews',
                'first_name', 'last_name', 'email',
            ];

            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDirection);
            }

            $users = $query->paginate($request->get('per_page', 20));

            // Transform user data for admin view
            $users->getCollection()->transform(function ($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'is_active' => $user->is_active,
                    'is_admin' => $user->is_admin,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'last_login_at' => $user->last_login_at,
                    'last_activity_at' => $user->last_activity_at,
                    'is_locked' => $user->isLocked(),
                    'locked_until' => $user->locked_until,
                    'failed_login_attempts' => $user->failed_login_attempts,
                    'cached_average_rating' => $user->cached_average_rating,
                    'cached_total_reviews' => $user->cached_total_reviews,
                    'jobs_count' => $user->jobs_count,
                    'skills_count' => $user->skills_count,
                    'received_reviews_count' => $user->received_reviews_count,
                    'payments_made_count' => $user->payments_made_count,
                    'payments_received_count' => $user->payments_received_count,
                    'deleted_at' => $user->deleted_at,
                ];
            });

            return response()->json([
                'message' => 'Users retrieved successfully',
                'data' => $users,
                'filters' => [
                    'total_users' => User::count(),
                    'active_users' => User::active()->count(),
                    'inactive_users' => User::where('is_active', false)->count(),
                    'deleted_users' => User::onlyTrashed()->count(),
                    'locked_users' => User::whereNotNull('locked_until')
                        ->where('locked_until', '>', now())
                        ->count(),
                    'admin_users' => User::admins()->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Admin users retrieval error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to retrieve users',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Perform bulk operations on users.
     */
    public function bulkUserActions(BulkUserActionRequest $request): JsonResponse
    {
        $this->authorize('admin.users.manage');

        // Validation is handled by BulkUserActionRequest

        try {
            DB::beginTransaction();

            $userIds = $request->user_ids;
            $action = $request->action;
            $affectedCount = 0;

            switch ($action) {
                case 'activate':
                    $affectedCount = User::whereIn('id', $userIds)
                        ->update(['is_active' => true]);

                    break;

                case 'deactivate':
                    $affectedCount = User::whereIn('id', $userIds)
                        ->where('is_admin', false) // Prevent deactivating admins
                        ->update(['is_active' => false]);

                    break;

                case 'delete':
                    $affectedCount = User::whereIn('id', $userIds)
                        ->where('is_admin', false) // Prevent deleting admins
                        ->delete();

                    break;

                case 'restore':
                    $affectedCount = User::onlyTrashed()
                        ->whereIn('id', $userIds)
                        ->restore();

                    break;

                case 'unlock':
                    $affectedCount = User::whereIn('id', $userIds)
                        ->update([
                            'locked_until' => null,
                            'failed_login_attempts' => 0,
                        ]);

                    break;
            }

            // Log the bulk action
            Log::info('Admin bulk user action', [
                'admin_id' => auth()->id(),
                'action' => $action,
                'user_ids' => $userIds,
                'affected_count' => $affectedCount,
            ]);

            DB::commit();

            return response()->json([
                'message' => "Bulk {$action} completed successfully",
                'affected_count' => $affectedCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin bulk user action error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Bulk action failed',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get content moderation queue with approval workflows.
     */
    public function contentModeration(Request $request): JsonResponse
    {
        $this->authorize('admin.content.moderate');

        try {
            $contentType = $request->get('type', 'all');
            $status = $request->get('status', 'pending');

            $moderationData = [];

            // Get flagged reviews
            if ($contentType === 'all' || $contentType === 'reviews') {
                $reviewsQuery = Review::with(['reviewer', 'reviewee', 'job'])
                    ->where('is_flagged', true);

                if ($status === 'pending') {
                    $reviewsQuery->whereNull('moderated_at');
                } elseif ($status === 'approved') {
                    $reviewsQuery->where('is_approved', true);
                } elseif ($status === 'rejected') {
                    $reviewsQuery->where('is_approved', false);
                }

                $moderationData['reviews'] = $reviewsQuery
                    ->orderBy('created_at', 'desc')
                    ->paginate(10);
            }

            // Get flagged jobs
            if ($contentType === 'all' || $contentType === 'jobs') {
                $jobsQuery = DB::table('job_postings')
                    ->join('users', 'job_postings.user_id', '=', 'users.id')
                    ->join('categories', 'job_postings.category_id', '=', 'categories.id')
                    ->where('job_postings.is_flagged', true);

                if ($status === 'pending') {
                    $jobsQuery->whereNull('job_postings.moderated_at');
                } elseif ($status === 'approved') {
                    $jobsQuery->where('job_postings.is_approved', true);
                } elseif ($status === 'rejected') {
                    $jobsQuery->where('job_postings.is_approved', false);
                }

                $moderationData['jobs'] = $jobsQuery
                    ->select([
                        'job_postings.*',
                        'users.first_name',
                        'users.last_name',
                        'users.email',
                        'categories.name as category_name',
                    ])
                    ->orderBy('job_postings.created_at', 'desc')
                    ->paginate(10);
            }

            // Get flagged skills
            if ($contentType === 'all' || $contentType === 'skills') {
                $skillsQuery = DB::table('skills')
                    ->join('users', 'skills.user_id', '=', 'users.id')
                    ->join('categories', 'skills.category_id', '=', 'categories.id')
                    ->where('skills.is_flagged', true);

                if ($status === 'pending') {
                    $skillsQuery->whereNull('skills.moderated_at');
                } elseif ($status === 'approved') {
                    $skillsQuery->where('skills.is_approved', true);
                } elseif ($status === 'rejected') {
                    $skillsQuery->where('skills.is_approved', false);
                }

                $moderationData['skills'] = $skillsQuery
                    ->select([
                        'skills.*',
                        'users.first_name',
                        'users.last_name',
                        'users.email',
                        'categories.name as category_name',
                    ])
                    ->orderBy('skills.created_at', 'desc')
                    ->paginate(10);
            }

            // Get moderation statistics
            $stats = [
                'pending_reviews' => Review::where('is_flagged', true)
                    ->whereNull('moderated_at')
                    ->count(),
                'pending_jobs' => DB::table('job_postings')
                    ->where('is_flagged', true)
                    ->whereNull('moderated_at')
                    ->count(),
                'pending_skills' => DB::table('skills')
                    ->where('is_flagged', true)
                    ->whereNull('moderated_at')
                    ->count(),
                'total_pending' => 0,
            ];

            $stats['total_pending'] = $stats['pending_reviews'] +
                                     $stats['pending_jobs'] +
                                     $stats['pending_skills'];

            return response()->json([
                'message' => 'Content moderation data retrieved successfully',
                'data' => $moderationData,
                'statistics' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Admin content moderation error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to retrieve content moderation data',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Moderate content (approve/reject).
     */
    public function moderateContent(ModerateContentRequest $request): JsonResponse
    {
        $this->authorize('admin.content.moderate');

        // Validation is handled by ModerateContentRequest

        try {
            DB::beginTransaction();

            $contentType = $request->content_type;
            $contentId = $request->content_id;
            $action = $request->action;
            $reason = $request->reason;

            $isApproved = $action === 'approve';

            switch ($contentType) {
                case 'review':
                    $content = Review::findOrFail($contentId);
                    $content->update([
                        'is_approved' => $isApproved,
                        'moderated_at' => now(),
                        'moderated_by' => auth()->id(),
                        'moderation_reason' => $reason,
                    ]);

                    break;

                case 'job':
                    DB::table('job_postings')
                        ->where('id', $contentId)
                        ->update([
                            'is_approved' => $isApproved,
                            'moderated_at' => now(),
                            'moderated_by' => auth()->id(),
                            'moderation_reason' => $reason,
                        ]);

                    break;

                case 'skill':
                    DB::table('skills')
                        ->where('id', $contentId)
                        ->update([
                            'is_approved' => $isApproved,
                            'moderated_at' => now(),
                            'moderated_by' => auth()->id(),
                            'moderation_reason' => $reason,
                        ]);

                    break;
            }

            // Log the moderation action
            Log::info('Admin content moderation', [
                'admin_id' => auth()->id(),
                'content_type' => $contentType,
                'content_id' => $contentId,
                'action' => $action,
                'reason' => $reason,
            ]);

            DB::commit();

            return response()->json([
                'message' => "Content {$action}d successfully",
                'action' => $action,
                'content_type' => $contentType,
                'content_id' => $contentId,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin content moderation error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Content moderation failed',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get dispute resolution data with evidence handling.
     */
    public function disputes(Request $request): JsonResponse
    {
        $this->authorize('admin.disputes.handle');

        try {
            $status = $request->get('status', 'open');
            $priority = $request->get('priority');

            $disputesQuery = Payment::with([
                'job.user',
                'payer',
                'payee',
                'job.category',
            ])
                ->where('status', 'disputed')
                ->whereNotNull('dispute_reason');

            // Filter by dispute status
            if ($status === 'open') {
                $disputesQuery->whereNull('dispute_resolved_at');
            } elseif ($status === 'resolved') {
                $disputesQuery->whereNotNull('dispute_resolved_at');
            }

            // Filter by priority
            if ($priority) {
                $disputesQuery->where('dispute_priority', $priority);
            }

            $disputes = $disputesQuery
                ->orderBy('dispute_created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            // Transform dispute data
            $disputes->getCollection()->transform(function ($payment) {
                return [
                    'id' => $payment->id,
                    'job' => [
                        'id' => $payment->job->id,
                        'title' => $payment->job->title,
                        'category' => $payment->job->category->name,
                        'client' => [
                            'id' => $payment->job->user->id,
                            'name' => $payment->job->user->full_name,
                            'email' => $payment->job->user->email,
                        ],
                    ],
                    'amount' => $payment->amount,
                    'platform_fee' => $payment->platform_fee,
                    'payer' => [
                        'id' => $payment->payer->id,
                        'name' => $payment->payer->full_name,
                        'email' => $payment->payer->email,
                    ],
                    'payee' => [
                        'id' => $payment->payee->id,
                        'name' => $payment->payee->full_name,
                        'email' => $payment->payee->email,
                    ],
                    'dispute' => [
                        'reason' => $payment->dispute_reason,
                        'description' => $payment->dispute_description,
                        'evidence' => $payment->dispute_evidence,
                        'priority' => $payment->dispute_priority,
                        'created_at' => $payment->dispute_created_at,
                        'resolved_at' => $payment->dispute_resolved_at,
                        'resolution' => $payment->dispute_resolution,
                        'resolved_by' => $payment->dispute_resolved_by,
                    ],
                    'created_at' => $payment->created_at,
                ];
            });

            // Get dispute statistics
            $stats = [
                'total_disputes' => Payment::where('status', 'disputed')->count(),
                'open_disputes' => Payment::where('status', 'disputed')
                    ->whereNull('dispute_resolved_at')
                    ->count(),
                'resolved_disputes' => Payment::where('status', 'disputed')
                    ->whereNotNull('dispute_resolved_at')
                    ->count(),
                'high_priority' => Payment::where('status', 'disputed')
                    ->where('dispute_priority', 'high')
                    ->whereNull('dispute_resolved_at')
                    ->count(),
                'average_resolution_time' => Payment::where('status', 'disputed')
                    ->whereNotNull('dispute_resolved_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, dispute_created_at, dispute_resolved_at)) as avg_hours')
                    ->value('avg_hours'),
            ];

            return response()->json([
                'message' => 'Disputes retrieved successfully',
                'data' => $disputes,
                'statistics' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Admin disputes retrieval error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to retrieve disputes',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Resolve a dispute.
     */
    public function resolveDispute(ResolveDisputeRequest $request, int $paymentId): JsonResponse
    {
        $this->authorize('admin.disputes.handle');

        // Validation is handled by ResolveDisputeRequest

        try {
            DB::beginTransaction();

            $payment = Payment::where('status', 'disputed')
                ->whereNull('dispute_resolved_at')
                ->findOrFail($paymentId);

            $resolution = $request->resolution;
            $notes = $request->resolution_notes;

            // Process the resolution
            switch ($resolution) {
                case 'refund_full':
                    $payment->update([
                        'status' => 'refunded',
                        'refund_amount' => $payment->amount,
                        'refunded_at' => now(),
                    ]);

                    break;

                case 'refund_partial':
                    $refundAmount = $request->refund_amount;
                    $payment->update([
                        'status' => 'partially_refunded',
                        'refund_amount' => $refundAmount,
                        'refunded_at' => now(),
                    ]);

                    break;

                case 'release_full':
                    $payment->update([
                        'status' => 'released',
                        'released_at' => now(),
                    ]);

                    break;

                case 'release_partial':
                    $releaseAmount = $request->release_amount;
                    $payment->update([
                        'status' => 'partially_released',
                        'released_amount' => $releaseAmount,
                        'released_at' => now(),
                    ]);

                    break;

                case 'no_action':
                    // Keep status as disputed but mark as resolved
                    break;
            }

            // Update dispute resolution fields
            $payment->update([
                'dispute_resolved_at' => now(),
                'dispute_resolved_by' => auth()->id(),
                'dispute_resolution' => $resolution,
                'dispute_resolution_notes' => $notes,
            ]);

            // Log the dispute resolution
            Log::info('Admin dispute resolution', [
                'admin_id' => auth()->id(),
                'payment_id' => $paymentId,
                'resolution' => $resolution,
                'notes' => $notes,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Dispute resolved successfully',
                'resolution' => $resolution,
                'payment_id' => $paymentId,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin dispute resolution error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Dispute resolution failed',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get system health monitoring data with alerts.
     */
    public function systemHealth(): JsonResponse
    {
        $this->authorize('admin.analytics.view');

        try {
            $healthData = [
                'database' => $this->checkDatabaseHealth(),
                'cache' => $this->checkCacheHealth(),
                'queue' => $this->checkQueueHealth(),
                'storage' => $this->checkStorageHealth(),
                'api_performance' => $this->getApiPerformanceMetrics(),
                'error_rates' => $this->getErrorRates(),
                'active_sessions' => $this->getActiveSessionsCount(),
                'system_load' => $this->getSystemLoadMetrics(),
            ];

            // Determine overall health status
            $overallHealth = $this->calculateOverallHealth($healthData);

            return response()->json([
                'message' => 'System health data retrieved successfully',
                'overall_status' => $overallHealth['status'],
                'health_score' => $overallHealth['score'],
                'data' => $healthData,
                'alerts' => $this->getSystemAlerts($healthData),
                'last_updated' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Admin system health error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to retrieve system health data',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get audit log with detailed activity tracking.
     */
    public function auditLog(Request $request): JsonResponse
    {
        $this->authorize('admin.logs.audit');

        try {
            // For now, we'll use Laravel's built-in logging
            // In a production system, you'd want a dedicated audit log table

            $logEntries = collect();
            $logFile = storage_path('logs/laravel.log');

            if (file_exists($logFile)) {
                $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $logs = array_slice($logs, -1000); // Get last 1000 entries

                foreach ($logs as $log) {
                    if (strpos($log, 'Admin') !== false || strpos($log, 'audit') !== false) {
                        $logEntries->push([
                            'timestamp' => $this->extractTimestamp($log),
                            'level' => $this->extractLogLevel($log),
                            'message' => $this->extractLogMessage($log),
                            'context' => $this->extractLogContext($log),
                        ]);
                    }
                }
            }

            // Add database-based audit entries (user actions, etc.)
            $userActions = $this->getUserAuditActions($request);
            $paymentActions = $this->getPaymentAuditActions($request);
            $moderationActions = $this->getModerationAuditActions($request);

            $auditData = [
                'log_entries' => $logEntries->sortByDesc('timestamp')->take(50)->values(),
                'user_actions' => $userActions,
                'payment_actions' => $paymentActions,
                'moderation_actions' => $moderationActions,
                'summary' => [
                    'total_entries' => $logEntries->count(),
                    'error_count' => $logEntries->where('level', 'ERROR')->count(),
                    'warning_count' => $logEntries->where('level', 'WARNING')->count(),
                    'info_count' => $logEntries->where('level', 'INFO')->count(),
                ],
            ];

            return response()->json([
                'message' => 'Audit log retrieved successfully',
                'data' => $auditData,
            ]);
        } catch (\Exception $e) {
            Log::error('Admin audit log error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to retrieve audit log',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    // Private helper methods for dashboard data

    private function getDashboardOverview(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::active()->count(),
            'total_jobs' => Job::count(),
            'active_jobs' => Job::where('status', 'open')->count(),
            'total_skills' => DB::table('skills')->count(),
            'total_payments' => Payment::count(),
            'total_reviews' => Review::count(),
            'platform_revenue' => Payment::where('status', 'released')
                ->sum('platform_fee'),
        ];
    }

    private function getRecentActivity(): array
    {
        return [
            'new_users_today' => User::whereDate('created_at', today())->count(),
            'new_jobs_today' => Job::whereDate('created_at', today())->count(),
            'payments_today' => Payment::whereDate('created_at', today())->count(),
            'reviews_today' => Review::whereDate('created_at', today())->count(),
            'recent_registrations' => User::latest()->take(5)->get(['id', 'first_name', 'last_name', 'email', 'created_at']),
        ];
    }

    private function getPendingIssues(): array
    {
        return [
            'pending_disputes' => Payment::where('status', 'disputed')
                ->whereNull('dispute_resolved_at')
                ->count(),
            'flagged_content' => Review::where('is_flagged', true)
                ->whereNull('moderated_at')
                ->count() +
                               Job::where('is_flagged', true)
                                   ->whereNull('moderated_at')
                                   ->count(),
            'locked_accounts' => User::whereNotNull('locked_until')
                ->where('locked_until', '>', now())
                ->count(),
            'failed_payments' => Payment::where('status', 'failed')
                ->whereDate('created_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }

    private function getFinancialSummary(): array
    {
        $today = today();
        $thisMonth = now()->startOfMonth();

        return [
            'revenue_today' => Payment::where('status', 'released')
                ->whereDate('created_at', $today)
                ->sum('platform_fee'),
            'revenue_this_month' => Payment::where('status', 'released')
                ->where('created_at', '>=', $thisMonth)
                ->sum('platform_fee'),
            'total_volume_today' => Payment::whereDate('created_at', $today)
                ->sum('amount'),
            'total_volume_this_month' => Payment::where('created_at', '>=', $thisMonth)
                ->sum('amount'),
            'pending_payouts' => Payment::where('status', 'held')->sum('amount'),
            'refunded_amount' => Payment::where('status', 'refunded')
                ->where('created_at', '>=', $thisMonth)
                ->sum('refund_amount'),
        ];
    }

    private function getUserMetrics(): array
    {
        return [
            'user_growth_rate' => $this->calculateUserGrowthRate(),
            'active_user_percentage' => (User::active()->count() / max(User::count(), 1)) * 100,
            'average_rating' => Review::avg('rating') ?? 0,
            'user_retention_rate' => $this->calculateUserRetentionRate(),
        ];
    }

    private function getPlatformHealth(): array
    {
        return [
            'uptime_percentage' => 99.9, // This would come from monitoring service
            'average_response_time' => 150, // milliseconds
            'error_rate' => 0.1, // percentage
            'active_connections' => rand(100, 500), // This would come from server metrics
        ];
    }

    private function calculateUserGrowthRate(): float
    {
        $thisMonth = User::whereMonth('created_at', now()->month)->count();
        $lastMonth = User::whereMonth('created_at', now()->subMonth()->month)->count();

        if ($lastMonth == 0) {
            return 100;
        }

        return (($thisMonth - $lastMonth) / $lastMonth) * 100;
    }

    private function calculateUserRetentionRate(): float
    {
        $totalUsers = User::count();
        $activeUsers = User::where('last_activity_at', '>=', now()->subDays(30))->count();

        if ($totalUsers == 0) {
            return 0;
        }

        return ($activeUsers / $totalUsers) * 100;
    }

    // System health check methods

    private function checkDatabaseHealth(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $responseTime = (microtime(true) - $start) * 1000;

            return [
                'status' => 'healthy',
                'response_time_ms' => round($responseTime, 2),
                'connections' => DB::select('SHOW STATUS LIKE "Threads_connected"')[0]->Value ?? 'unknown',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCacheHealth(): array
    {
        try {
            $start = microtime(true);
            Cache::put('health_check', 'test', 10);
            $value = Cache::get('health_check');
            $responseTime = (microtime(true) - $start) * 1000;

            return [
                'status' => $value === 'test' ? 'healthy' : 'unhealthy',
                'response_time_ms' => round($responseTime, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkQueueHealth(): array
    {
        try {
            // This would check queue status in a real implementation
            return [
                'status' => 'healthy',
                'pending_jobs' => 0,
                'failed_jobs' => 0,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkStorageHealth(): array
    {
        try {
            $diskSpace = disk_free_space(storage_path());
            $totalSpace = disk_total_space(storage_path());
            $usedPercentage = (($totalSpace - $diskSpace) / $totalSpace) * 100;

            return [
                'status' => $usedPercentage < 90 ? 'healthy' : 'warning',
                'used_percentage' => round($usedPercentage, 2),
                'free_space_gb' => round($diskSpace / (1024 * 1024 * 1024), 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getApiPerformanceMetrics(): array
    {
        // In a real implementation, this would come from APM tools
        return [
            'average_response_time' => 150,
            'requests_per_minute' => rand(50, 200),
            'error_rate' => 0.1,
            'slowest_endpoints' => [
                '/api/search/jobs' => 300,
                '/api/admin/dashboard' => 250,
                '/api/payments/history' => 200,
            ],
        ];
    }

    private function getErrorRates(): array
    {
        return [
            '4xx_errors' => 2.1,
            '5xx_errors' => 0.1,
            'total_errors' => 2.2,
        ];
    }

    private function getActiveSessionsCount(): int
    {
        return DB::table('personal_access_tokens')
            ->where('last_used_at', '>=', now()->subHours(1))
            ->count();
    }

    private function getSystemLoadMetrics(): array
    {
        return [
            'cpu_usage' => rand(20, 80),
            'memory_usage' => rand(40, 90),
            'disk_io' => rand(10, 50),
        ];
    }

    private function calculateOverallHealth(array $healthData): array
    {
        $healthyCount = 0;
        $totalChecks = 0;

        foreach ($healthData as $check) {
            if (is_array($check) && isset($check['status'])) {
                $totalChecks++;
                if ($check['status'] === 'healthy') {
                    $healthyCount++;
                }
            }
        }

        $score = $totalChecks > 0 ? ($healthyCount / $totalChecks) * 100 : 0;

        $status = 'healthy';
        if ($score < 50) {
            $status = 'critical';
        } elseif ($score < 80) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'score' => round($score, 1),
        ];
    }

    private function getSystemAlerts(array $healthData): array
    {
        $alerts = [];

        foreach ($healthData as $component => $data) {
            if (is_array($data) && isset($data['status']) && $data['status'] !== 'healthy') {
                $alerts[] = [
                    'component' => $component,
                    'status' => $data['status'],
                    'message' => $data['error'] ?? "Component {$component} is not healthy",
                    'severity' => $data['status'] === 'critical' ? 'high' : 'medium',
                ];
            }
        }

        return $alerts;
    }

    // Audit log helper methods

    private function extractTimestamp(string $log): ?string
    {
        if (preg_match('/\[(.*?)\]/', $log, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractLogLevel(string $log): string
    {
        if (preg_match('/\] (\w+):/', $log, $matches)) {
            return $matches[1];
        }

        return 'INFO';
    }

    private function extractLogMessage(string $log): string
    {
        if (preg_match('/\] \w+: (.*)/', $log, $matches)) {
            return $matches[1];
        }

        return $log;
    }

    private function extractLogContext(string $log): ?array
    {
        // Extract JSON context if present
        if (preg_match('/\{.*\}/', $log, $matches)) {
            $json = json_decode($matches[0], true);

            return is_array($json) ? $json : null;
        }

        return null;
    }

    private function getUserAuditActions(Request $request): array
    {
        // Get recent user-related actions from database
        return User::withTrashed()
            ->where('updated_at', '>=', now()->subDays(7))
            ->orderBy('updated_at', 'desc')
            ->take(20)
            ->get(['id', 'email', 'is_active', 'updated_at', 'deleted_at'])
            ->map(function ($user) {
                return [
                    'type' => 'user_action',
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'action' => $user->deleted_at ? 'deleted' : ($user->is_active ? 'activated' : 'deactivated'),
                    'timestamp' => $user->updated_at,
                ];
            })
            ->toArray();
    }

    private function getPaymentAuditActions(Request $request): array
    {
        return Payment::with(['payer:id,email', 'payee:id,email'])
            ->where('updated_at', '>=', now()->subDays(7))
            ->orderBy('updated_at', 'desc')
            ->take(20)
            ->get()
            ->map(function ($payment) {
                return [
                    'type' => 'payment_action',
                    'payment_id' => $payment->id,
                    'payer_email' => $payment->payer->email,
                    'payee_email' => $payment->payee->email,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'timestamp' => $payment->updated_at,
                ];
            })
            ->toArray();
    }

    private function getModerationAuditActions(Request $request): array
    {
        return Review::whereNotNull('moderated_at')
            ->where('moderated_at', '>=', now()->subDays(7))
            ->with(['reviewer:id,email', 'reviewee:id,email'])
            ->orderBy('moderated_at', 'desc')
            ->take(20)
            ->get()
            ->map(function ($review) {
                return [
                    'type' => 'moderation_action',
                    'review_id' => $review->id,
                    'reviewer_email' => $review->reviewer->email,
                    'reviewee_email' => $review->reviewee->email,
                    'action' => $review->is_approved ? 'approved' : 'rejected',
                    'timestamp' => $review->moderated_at,
                ];
            })
            ->toArray();
    }
}
