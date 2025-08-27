<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdminPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can access the admin dashboard.
     */
    public function viewDashboard(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can manage users.
     */
    public function manageUsers(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view user details.
     */
    public function viewUserDetails(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can suspend/activate users.
     */
    public function suspendUsers(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can delete users.
     */
    public function deleteUsers(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can manage job listings.
     */
    public function manageJobs(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can moderate content.
     */
    public function moderateContent(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can handle disputes.
     */
    public function handleDisputes(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view financial reports.
     */
    public function viewFinancialReports(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can manage payments.
     */
    public function managePayments(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can process refunds.
     */
    public function processRefunds(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view system analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can manage categories.
     */
    public function manageCategories(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view audit logs.
     */
    public function viewAuditLogs(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can manage site settings.
     */
    public function manageSiteSettings(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can send system notifications.
     */
    public function sendNotifications(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can export data.
     */
    public function exportData(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can import data.
     */
    public function importData(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can manage admin roles.
     */
    public function manageAdminRoles(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view flagged content.
     */
    public function viewFlaggedContent(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can ban users.
     */
    public function banUsers(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can manage platform fees.
     */
    public function managePlatformFees(User $user): bool
    {
        return $user->is_admin;
    }
}
