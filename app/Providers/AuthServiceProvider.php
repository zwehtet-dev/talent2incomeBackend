<?php

namespace App\Providers;

use App\Models\Job;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Review;
use App\Models\SavedSearch;
use App\Models\Skill;
use App\Models\User;
use App\Policies\AdminPolicy;
use App\Policies\JobPolicy;
use App\Policies\MessagePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\SavedSearchPolicy;
use App\Policies\SkillPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Job::class => JobPolicy::class,
        Skill::class => SkillPolicy::class,
        Message::class => MessagePolicy::class,
        Payment::class => PaymentPolicy::class,
        Review::class => ReviewPolicy::class,
        SavedSearch::class => SavedSearchPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Register AdminPolicy for gate-based authorization
        $this->registerAdminGates();
    }

    /**
     * Register admin-specific gates.
     */
    protected function registerAdminGates(): void
    {
        $adminPolicy = new AdminPolicy();

        // Dashboard access
        \Gate::define('admin.dashboard', [$adminPolicy, 'viewDashboard']);

        // User management
        \Gate::define('admin.users.manage', [$adminPolicy, 'manageUsers']);
        \Gate::define('admin.users.view', [$adminPolicy, 'viewUserDetails']);
        \Gate::define('admin.users.suspend', [$adminPolicy, 'suspendUsers']);
        \Gate::define('admin.users.delete', [$adminPolicy, 'deleteUsers']);
        \Gate::define('admin.users.ban', [$adminPolicy, 'banUsers']);

        // Content management
        \Gate::define('admin.jobs.manage', [$adminPolicy, 'manageJobs']);
        \Gate::define('admin.content.moderate', [$adminPolicy, 'moderateContent']);
        \Gate::define('admin.content.flagged', [$adminPolicy, 'viewFlaggedContent']);

        // Financial management
        \Gate::define('admin.payments.manage', [$adminPolicy, 'managePayments']);
        \Gate::define('admin.payments.refund', [$adminPolicy, 'processRefunds']);
        \Gate::define('admin.reports.financial', [$adminPolicy, 'viewFinancialReports']);
        \Gate::define('admin.fees.manage', [$adminPolicy, 'managePlatformFees']);

        // Dispute handling
        \Gate::define('admin.disputes.handle', [$adminPolicy, 'handleDisputes']);

        // Analytics and reporting
        \Gate::define('admin.analytics.view', [$adminPolicy, 'viewAnalytics']);
        \Gate::define('admin.logs.audit', [$adminPolicy, 'viewAuditLogs']);

        // System management
        \Gate::define('admin.categories.manage', [$adminPolicy, 'manageCategories']);
        \Gate::define('admin.settings.manage', [$adminPolicy, 'manageSiteSettings']);
        \Gate::define('admin.notifications.send', [$adminPolicy, 'sendNotifications']);
        \Gate::define('admin.roles.manage', [$adminPolicy, 'manageAdminRoles']);

        // Data management
        \Gate::define('admin.data.export', [$adminPolicy, 'exportData']);
        \Gate::define('admin.data.import', [$adminPolicy, 'importData']);
    }
}
