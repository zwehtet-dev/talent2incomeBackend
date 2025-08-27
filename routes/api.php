<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SavedSearchController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health Check
Route::get('/health', [HealthController::class, 'simple']);
Route::get('/health/detailed', [HealthController::class, 'check']);

// Debug registration
Route::post('/debug/register', function (\Illuminate\Http\Request $request) {
    try {
        $user = \App\Models\User::create([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        return response()->json([
            'success' => true,
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ], 201);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

// API Version Information
Route::get('/', function () {
    return response()->json([
        'name' => 'Talent2Income API',
        'version' => '1.0.0',
        'current_version' => 'v1',
        'supported_versions' => ['v1'],
        'documentation' => url('/api/documentation'),
        'status' => 'operational',
    ]);
})->name('api.info');

Route::get('/versions', function () {
    $versionService = app(\App\Services\ApiVersionService::class);

    return response()->json([
        'supported_versions' => $versionService->getSupportedVersions(),
        'current_version' => \App\Services\ApiVersionService::CURRENT_VERSION,
        'default_version' => \App\Services\ApiVersionService::DEFAULT_VERSION,
    ]);
})->name('api.versions');

// Authentication routes with rate limiting
Route::prefix('auth')->middleware(['api.version:v1'])->group(function () {
    // OAuth routes (public)
    Route::get('google', [\App\Http\Controllers\Api\OAuthController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('google/callback', [\App\Http\Controllers\Api\OAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

    // Public authentication endpoints with strict rate limiting
    Route::middleware(['rate.limit:5,1'])->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
    });

    // Email verification (less strict rate limiting)
    Route::middleware(['rate.limit:10,1'])->group(function () {
        Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->name('verification.verify');
        Route::post('resend-verification', [AuthController::class, 'resendVerification'])
            ->name('verification.send');
    });

    // Authenticated routes
    Route::middleware(['auth:sanctum', 'auth.custom'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');

        // Session management
        Route::get('sessions', [AuthController::class, 'activeSessions'])->name('auth.sessions');
        Route::delete('sessions/{tokenId}', [AuthController::class, 'revokeSession'])->name('auth.revoke-session');

        // Two-factor authentication preparation
        Route::post('2fa/prepare', [AuthController::class, 'prepareTwoFactor'])->name('auth.2fa.prepare');

        // Phone verification routes
        Route::prefix('phone')->group(function () {
            Route::post('send-verification', [\App\Http\Controllers\Api\OAuthController::class, 'sendPhoneVerification'])->name('auth.phone.send-verification');
            Route::post('verify', [\App\Http\Controllers\Api\OAuthController::class, 'verifyPhoneCode'])->name('auth.phone.verify');
            Route::get('status', [\App\Http\Controllers\Api\OAuthController::class, 'getPhoneVerificationStatus'])->name('auth.phone.status');
        });

        // OAuth management routes
        Route::prefix('oauth')->group(function () {
            Route::get('status', [\App\Http\Controllers\Api\OAuthController::class, 'getOAuthStatus'])->name('auth.oauth.status');
            Route::delete('google/unlink', [\App\Http\Controllers\Api\OAuthController::class, 'unlinkGoogle'])->name('auth.google.unlink');
        });
    });
});

// Protected API routes
Route::middleware(['auth:sanctum', 'auth.custom', 'rate.limit:60,1', 'api.version:v1'])->group(function () {
    // User profile routes
    Route::prefix('users')->group(function () {
        Route::get('profile', [UserController::class, 'profile'])->name('users.profile');
        Route::put('profile', [UserController::class, 'updateProfile'])->name('users.update-profile');
        Route::post('avatar', [UserController::class, 'uploadAvatar'])->name('users.upload-avatar');
        Route::get('statistics', [UserController::class, 'statistics'])->name('users.statistics');
        Route::get('search', [UserController::class, 'search'])->name('users.search');
        Route::get('online', [UserController::class, 'onlineUsers'])->name('users.online');
        Route::get('{user}/online-status', [UserController::class, 'onlineStatus'])->name('users.online-status');
        Route::get('{user}', [UserController::class, 'show'])->name('users.show');
    });

    // Category routes
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/with-skill-counts', [CategoryController::class, 'withSkillCounts'])->name('categories.with-skill-counts');
        Route::get('/with-job-counts', [CategoryController::class, 'withJobCounts'])->name('categories.with-job-counts');
        Route::get('/{category}', [CategoryController::class, 'show'])->name('categories.show');
    });

    // Job routes
    Route::prefix('jobs')->group(function () {
        Route::get('/', [JobController::class, 'index'])->name('jobs.index');
        Route::post('/', [JobController::class, 'store'])->name('jobs.store');
        Route::get('/search', [JobController::class, 'search'])->name('jobs.search');
        Route::get('/my-jobs', [JobController::class, 'myJobs'])->name('jobs.my-jobs');
        Route::get('/assigned', [JobController::class, 'assignedJobs'])->name('jobs.assigned');
        Route::get('/{job}', [JobController::class, 'show'])->name('jobs.show');
        Route::put('/{job}', [JobController::class, 'update'])->name('jobs.update');
        Route::delete('/{job}', [JobController::class, 'destroy'])->name('jobs.destroy');
    });

    // Skill routes
    Route::prefix('skills')->group(function () {
        Route::get('/', [SkillController::class, 'index'])->name('skills.index');
        Route::post('/', [SkillController::class, 'store'])->name('skills.store');
        Route::get('/search', [SkillController::class, 'search'])->name('skills.search');
        Route::get('/my-skills', [SkillController::class, 'mySkills'])->name('skills.my-skills');
        Route::get('/category/{categoryId}', [SkillController::class, 'byCategory'])->name('skills.by-category');
        Route::get('/{skill}', [SkillController::class, 'show'])->name('skills.show');
        Route::put('/{skill}', [SkillController::class, 'update'])->name('skills.update');
        Route::delete('/{skill}', [SkillController::class, 'destroy'])->name('skills.destroy');
        Route::patch('/{skill}/toggle-availability', [SkillController::class, 'toggleAvailability'])->name('skills.toggle-availability');
    });

    // Message routes
    Route::prefix('messages')->group(function () {
        Route::get('conversations', [MessageController::class, 'conversations'])->name('messages.conversations');
        Route::get('conversation/{user}', [MessageController::class, 'conversation'])->name('messages.conversation');
        Route::post('/', [MessageController::class, 'store'])->name('messages.store');
        Route::post('mark-read/{user}', [MessageController::class, 'markAsRead'])->name('messages.mark-read');
        Route::get('search', [MessageController::class, 'search'])->name('messages.search');
        Route::get('unread-count', [MessageController::class, 'unreadCount'])->name('messages.unread-count');

        // Real-time features
        Route::post('typing/{user}', [MessageController::class, 'typing'])->name('messages.typing');

        // User blocking
        Route::post('block', [MessageController::class, 'blockUser'])->name('messages.block-user');
        Route::delete('unblock/{user}', [MessageController::class, 'unblockUser'])->name('messages.unblock-user');
        Route::get('blocked', [MessageController::class, 'blockedUsers'])->name('messages.blocked-users');
    });

    // Payment routes
    Route::prefix('payments')->group(function () {
        Route::post('create', [PaymentController::class, 'create'])->name('payments.create');
        Route::get('history', [PaymentController::class, 'history'])->name('payments.history');
        Route::get('statistics', [PaymentController::class, 'statistics'])->name('payments.statistics');
        Route::get('{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('{payment}/release', [PaymentController::class, 'release'])->name('payments.release');
        Route::post('{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');
    });

    // Review routes
    Route::prefix('reviews')->group(function () {
        Route::post('/', [ReviewController::class, 'store'])->name('reviews.store');
        Route::get('/needing-moderation', [ReviewController::class, 'needingModeration'])->name('reviews.needing-moderation');
        Route::get('/platform-statistics', [ReviewController::class, 'platformStatistics'])->name('reviews.platform-statistics');
        Route::get('/user/{user}/statistics', [ReviewController::class, 'statistics'])->name('reviews.user-statistics');
        Route::get('/{review}', [ReviewController::class, 'show'])->name('reviews.show');
        Route::put('/{review}', [ReviewController::class, 'update'])->name('reviews.update');
        Route::delete('/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
        Route::post('/{review}/moderate', [ReviewController::class, 'moderate'])->name('reviews.moderate');
        Route::post('/{review}/report', [ReviewController::class, 'report'])->name('reviews.report');
        Route::post('/{review}/respond', [ReviewController::class, 'respond'])->name('reviews.respond');
    });

    // Rating routes
    Route::prefix('ratings')->group(function () {
        Route::get('my-stats', [RatingController::class, 'getMyStats'])->name('ratings.my-stats');
        Route::get('user/{userId}/stats', [RatingController::class, 'getUserStats'])->name('ratings.user-stats');
        Route::get('user/{userId}/history', [RatingController::class, 'getRatingHistory'])->name('ratings.user-history');
        Route::get('user/{userId}/ranking', [RatingController::class, 'getUserRanking'])->name('ratings.user-ranking');
        Route::get('top-rated', [RatingController::class, 'getTopRatedUsers'])->name('ratings.top-rated');
        Route::get('trends', [RatingController::class, 'getRatingTrends'])->name('ratings.trends');
    });

    // Advanced search routes
    Route::prefix('search')->group(function () {
        Route::get('jobs', [SearchController::class, 'searchJobs'])->name('search.jobs');
        Route::get('skills', [SearchController::class, 'searchSkills'])->name('search.skills');
        Route::get('all', [SearchController::class, 'searchAll'])->name('search.all');
        Route::get('suggestions', [SearchController::class, 'suggestions'])->name('search.suggestions');
        Route::get('popular', [SearchController::class, 'popularSearches'])->name('search.popular');
        Route::get('trending', [SearchController::class, 'trending'])->name('search.trending');
        Route::get('facets', [SearchController::class, 'facets'])->name('search.facets');
        Route::get('analytics', [SearchController::class, 'analytics'])->name('search.analytics');

        // Advanced filtering endpoints
        Route::get('jobs/advanced', [SearchController::class, 'advancedJobSearch'])->name('search.jobs.advanced');
        Route::get('skills/advanced', [SearchController::class, 'advancedSkillSearch'])->name('search.skills.advanced');
    });

    // Saved search routes
    Route::prefix('saved-searches')->group(function () {
        Route::get('/', [SavedSearchController::class, 'index'])->name('saved-searches.index');
        Route::post('/', [SavedSearchController::class, 'store'])->name('saved-searches.store');
        Route::get('/filter-options', [SavedSearchController::class, 'filterOptions'])->name('saved-searches.filter-options');
        Route::get('/{savedSearch}', [SavedSearchController::class, 'show'])->name('saved-searches.show');
        Route::put('/{savedSearch}', [SavedSearchController::class, 'update'])->name('saved-searches.update');
        Route::delete('/{savedSearch}', [SavedSearchController::class, 'destroy'])->name('saved-searches.destroy');
        Route::post('/{savedSearch}/execute', [SavedSearchController::class, 'execute'])->name('saved-searches.execute');
        Route::get('/{savedSearch}/new-results', [SavedSearchController::class, 'newResults'])->name('saved-searches.new-results');
        Route::patch('/{savedSearch}/toggle-notifications', [SavedSearchController::class, 'toggleNotifications'])->name('saved-searches.toggle-notifications');
    });
});

// Public review routes (outside authenticated group)
Route::prefix('reviews')->group(function () {
    Route::get('/', [ReviewController::class, 'index'])->name('reviews.index');
});

// Admin routes
Route::middleware(['auth:sanctum', 'auth.custom', 'admin', 'api.version:v1'])->prefix('admin')->group(function () {
    // Dashboard and overview
    Route::get('dashboard', [\App\Http\Controllers\Api\AdminController::class, 'dashboard'])->name('admin.dashboard');

    // User management
    Route::get('users', [\App\Http\Controllers\Api\AdminController::class, 'users'])->name('admin.users');
    Route::post('users/bulk-actions', [\App\Http\Controllers\Api\AdminController::class, 'bulkUserActions'])->name('admin.users.bulk-actions');

    // Content moderation
    Route::get('content-moderation', [\App\Http\Controllers\Api\AdminController::class, 'contentModeration'])->name('admin.content-moderation');
    Route::post('moderate-content', [\App\Http\Controllers\Api\AdminController::class, 'moderateContent'])->name('admin.moderate-content');

    // Dispute resolution
    Route::get('disputes', [\App\Http\Controllers\Api\AdminController::class, 'disputes'])->name('admin.disputes');
    Route::post('disputes/{payment}/resolve', [\App\Http\Controllers\Api\AdminController::class, 'resolveDispute'])->name('admin.disputes.resolve');

    // System health and monitoring
    Route::get('system-health', [\App\Http\Controllers\Api\AdminController::class, 'systemHealth'])->name('admin.system-health');

    // Audit logs
    Route::get('audit-log', [\App\Http\Controllers\Api\AdminController::class, 'auditLog'])->name('admin.audit-log');

    // Analytics routes (admin only)
    Route::prefix('analytics')->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\Api\AnalyticsController::class, 'dashboard'])->name('admin.analytics.dashboard');
        Route::get('revenue', [\App\Http\Controllers\Api\AnalyticsController::class, 'revenue'])->name('admin.analytics.revenue');
        Route::get('engagement', [\App\Http\Controllers\Api\AnalyticsController::class, 'engagement'])->name('admin.analytics.engagement');
        Route::get('cohorts', [\App\Http\Controllers\Api\AnalyticsController::class, 'cohorts'])->name('admin.analytics.cohorts');
        Route::get('system-health', [\App\Http\Controllers\Api\AnalyticsController::class, 'systemHealth'])->name('admin.analytics.system-health');

        // Report generation
        Route::post('generate-report', [\App\Http\Controllers\Api\AnalyticsController::class, 'generateReport'])->name('admin.analytics.generate-report');
        Route::get('reports', [\App\Http\Controllers\Api\AnalyticsController::class, 'reports'])->name('admin.analytics.reports');
        Route::get('reports/{report}', [\App\Http\Controllers\Api\AnalyticsController::class, 'getReport'])->name('admin.analytics.get-report');

        // Scheduled reports
        Route::post('scheduled-reports', [\App\Http\Controllers\Api\AnalyticsController::class, 'createScheduledReport'])->name('admin.analytics.create-scheduled-report');
        Route::get('scheduled-reports', [\App\Http\Controllers\Api\AnalyticsController::class, 'scheduledReports'])->name('admin.analytics.scheduled-reports');
        Route::put('scheduled-reports/{scheduledReport}', [\App\Http\Controllers\Api\AnalyticsController::class, 'updateScheduledReport'])->name('admin.analytics.update-scheduled-report');
        Route::delete('scheduled-reports/{scheduledReport}', [\App\Http\Controllers\Api\AnalyticsController::class, 'deleteScheduledReport'])->name('admin.analytics.delete-scheduled-report');
        Route::post('process-scheduled-reports', [\App\Http\Controllers\Api\AnalyticsController::class, 'processScheduledReports'])->name('admin.analytics.process-scheduled-reports');

        // System metrics
        Route::post('performance-metrics', [\App\Http\Controllers\Api\AnalyticsController::class, 'recordPerformanceMetrics'])->name('admin.analytics.record-performance-metrics');
        Route::post('calculate-analytics', [\App\Http\Controllers\Api\AnalyticsController::class, 'calculateAnalytics'])->name('admin.analytics.calculate-analytics');
    });

    // API Usage Analytics routes
    Route::prefix('usage')->group(function () {
        Route::get('analytics', [\App\Http\Controllers\Api\ApiUsageController::class, 'getAnalytics'])->name('admin.usage.analytics');
        Route::get('rate-limits', [\App\Http\Controllers\Api\ApiUsageController::class, 'getRateLimitViolations'])->name('admin.usage.rate-limits');
    });

    // Compliance routes (admin only)
    Route::prefix('compliance')->group(function () {
        Route::get('status', [\App\Http\Controllers\Api\ComplianceController::class, 'status'])->name('admin.compliance.status');
        Route::get('report', [\App\Http\Controllers\Api\ComplianceController::class, 'report'])->name('admin.compliance.report');
        Route::post('cleanup', [\App\Http\Controllers\Api\ComplianceController::class, 'cleanup'])->name('admin.compliance.cleanup');
        Route::get('audit-logs', [\App\Http\Controllers\Api\ComplianceController::class, 'auditLogs'])->name('admin.compliance.audit-logs');
        Route::post('verify-audit-integrity', [\App\Http\Controllers\Api\ComplianceController::class, 'verifyAuditIntegrity'])->name('admin.compliance.verify-audit-integrity');
        Route::get('retention-policies', [\App\Http\Controllers\Api\ComplianceController::class, 'retentionPolicies'])->name('admin.compliance.retention-policies');
        Route::post('export', [\App\Http\Controllers\Api\ComplianceController::class, 'export'])->name('admin.compliance.export');
    });

    // GDPR admin routes
    Route::prefix('gdpr')->group(function () {
        Route::get('requests', [\App\Http\Controllers\Api\GdprController::class, 'adminGetRequests'])->name('admin.gdpr.requests');
        Route::post('requests/{gdprRequest}/process', [\App\Http\Controllers\Api\GdprController::class, 'adminProcessRequest'])->name('admin.gdpr.process-request');
    });
});

// GDPR routes (authenticated users)
Route::middleware(['auth:sanctum', 'auth.custom', 'api.version:v1'])->prefix('gdpr')->group(function () {
    Route::post('requests', [\App\Http\Controllers\Api\GdprController::class, 'createRequest'])->name('gdpr.create-request');
    Route::post('verify', [\App\Http\Controllers\Api\GdprController::class, 'verifyRequest'])->name('gdpr.verify-request');
    Route::get('requests', [\App\Http\Controllers\Api\GdprController::class, 'getUserRequests'])->name('gdpr.user-requests');
    Route::get('requests/{gdprRequest}/download', [\App\Http\Controllers\Api\GdprController::class, 'downloadExport'])->name('gdpr.download-export');

    // Consent management
    Route::post('consent', [\App\Http\Controllers\Api\GdprController::class, 'recordConsent'])->name('gdpr.record-consent');
    Route::get('consent/status', [\App\Http\Controllers\Api\GdprController::class, 'getConsentStatus'])->name('gdpr.consent-status');
    Route::get('consent/history', [\App\Http\Controllers\Api\GdprController::class, 'getConsentHistory'])->name('gdpr.consent-history');
});
