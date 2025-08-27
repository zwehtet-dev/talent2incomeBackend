<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class JobPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view job listings
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Job $job): bool
    {
        // All users can view active jobs, only owner/assigned user can view inactive jobs
        return $job->status !== 'cancelled' ||
               $user->id === $job->user_id ||
               $user->id === $job->assigned_to ||
               $user->is_admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only verified and active users can create jobs
        // return $user->is_active && $user->email_verified_at !== null;
        return $user->is_active;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Job $job): bool
    {
        // Only job owner can update, and only if job is not completed or cancelled
        return $user->id === $job->user_id &&
               ! in_array($job->status, ['completed', 'cancelled']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Job $job): bool
    {
        // Job owner can delete if no one is assigned, admins can always delete
        return ($user->id === $job->user_id && $job->assigned_to === null) ||
               $user->is_admin;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Job $job): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Job $job): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can apply to the job.
     */
    public function apply(User $user, Job $job): bool
    {
        // Users cannot apply to their own jobs, job must be open, user must be verified
        return $user->id !== $job->user_id &&
               $job->status === 'open' &&
               $user->is_active &&
               $user->email_verified_at !== null;
    }

    /**
     * Determine whether the user can assign the job to someone.
     */
    public function assign(User $user, Job $job): bool
    {
        // Only job owner can assign, and job must be open
        return $user->id === $job->user_id && $job->status === 'open';
    }

    /**
     * Determine whether the user can mark the job as completed.
     */
    public function markCompleted(User $user, Job $job): bool
    {
        // Only job owner can mark as completed, and job must be in progress
        return $user->id === $job->user_id && $job->status === 'in_progress';
    }

    /**
     * Determine whether the user can cancel the job.
     */
    public function cancel(User $user, Job $job): bool
    {
        // Job owner can cancel if not completed, assigned user can cancel if in progress
        return ($user->id === $job->user_id && $job->status !== 'completed') ||
               ($user->id === $job->assigned_to && $job->status === 'in_progress') ||
               $user->is_admin;
    }

    /**
     * Determine whether the user can view job applications.
     */
    public function viewApplications(User $user, Job $job): bool
    {
        // Only job owner can view applications
        return $user->id === $job->user_id;
    }

    /**
     * Determine whether the user can moderate the job.
     */
    public function moderate(User $user, Job $job): bool
    {
        return $user->is_admin;
    }
}
