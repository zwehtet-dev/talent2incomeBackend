<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own profile or admins can view any profile
        return $user->id === $model->id || $user->is_admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only admins can create users through the API
        return $user->is_admin;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can only update their own profile
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Users can delete their own account or admins can delete any account
        return $user->id === $model->id || $user->is_admin;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view profile statistics.
     */
    public function viewStatistics(User $user, User $model): bool
    {
        // Users can view their own stats, others can view public stats
        return $user->id === $model->id || $model->is_active;
    }

    /**
     * Determine whether the user can view online status.
     */
    public function viewOnlineStatus(User $user, User $model): bool
    {
        // All authenticated users can view online status of active users
        return $model->is_active;
    }

    /**
     * Determine whether the user can update profile privacy settings.
     */
    public function updatePrivacy(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can block other users.
     */
    public function blockUser(User $user, User $targetUser): bool
    {
        // Users cannot block themselves or admins
        return $user->id !== $targetUser->id && ! $targetUser->is_admin;
    }

    /**
     * Determine whether the user can manage admin privileges.
     */
    public function manageAdminPrivileges(User $user): bool
    {
        return $user->is_admin;
    }
}
