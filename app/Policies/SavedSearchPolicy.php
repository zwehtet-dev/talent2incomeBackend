<?php

namespace App\Policies;

use App\Models\SavedSearch;
use App\Models\User;

class SavedSearchPolicy
{
    /**
     * Determine whether the user can view any saved searches.
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can view their own saved searches
    }

    /**
     * Determine whether the user can view the saved search.
     */
    public function view(User $user, SavedSearch $savedSearch): bool
    {
        return $user->id === $savedSearch->user_id;
    }

    /**
     * Determine whether the user can create saved searches.
     */
    public function create(User $user): bool
    {
        // For now, just allow creation without checking limits
        // The limit check can be done in the controller if needed
        return true;
    }

    /**
     * Determine whether the user can update the saved search.
     */
    public function update(User $user, SavedSearch $savedSearch): bool
    {
        return $user->id === $savedSearch->user_id;
    }

    /**
     * Determine whether the user can delete the saved search.
     */
    public function delete(User $user, SavedSearch $savedSearch): bool
    {
        return $user->id === $savedSearch->user_id;
    }

    /**
     * Determine whether the user can restore the saved search.
     */
    public function restore(User $user, SavedSearch $savedSearch): bool
    {
        return $user->id === $savedSearch->user_id;
    }

    /**
     * Determine whether the user can permanently delete the saved search.
     */
    public function forceDelete(User $user, SavedSearch $savedSearch): bool
    {
        return $user->id === $savedSearch->user_id;
    }
}
