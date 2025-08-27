<?php

namespace App\Policies;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SkillPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view skill listings
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Skill $skill): bool
    {
        // All users can view active skills, only owner can view inactive skills
        return $skill->is_active || $user->id === $skill->user_id || $user->is_admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only verified and active users can create skills
        return $user->is_active && $user->email_verified_at !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Skill $skill): bool
    {
        // Only skill owner can update their skills
        return $user->id === $skill->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Skill $skill): bool
    {
        // Skill owner can delete their skills, admins can delete any skill
        return $user->id === $skill->user_id || $user->is_admin;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Skill $skill): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Skill $skill): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can update availability status.
     */
    public function updateAvailability(User $user, Skill $skill): bool
    {
        // Only skill owner can update availability
        return $user->id === $skill->user_id;
    }

    /**
     * Determine whether the user can update pricing.
     */
    public function updatePricing(User $user, Skill $skill): bool
    {
        // Only skill owner can update pricing
        return $user->id === $skill->user_id;
    }

    /**
     * Determine whether the user can contact the skill provider.
     */
    public function contact(User $user, Skill $skill): bool
    {
        // Users cannot contact themselves, skill must be available, user must be verified
        return $user->id !== $skill->user_id &&
               $skill->is_available &&
               $skill->is_active &&
               $user->is_active &&
               $user->email_verified_at !== null;
    }

    /**
     * Determine whether the user can view skill statistics.
     */
    public function viewStatistics(User $user, Skill $skill): bool
    {
        // Only skill owner can view detailed statistics
        return $user->id === $skill->user_id;
    }

    /**
     * Determine whether the user can moderate the skill.
     */
    public function moderate(User $user, Skill $skill): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can feature the skill.
     */
    public function feature(User $user, Skill $skill): bool
    {
        return $user->is_admin;
    }
}
