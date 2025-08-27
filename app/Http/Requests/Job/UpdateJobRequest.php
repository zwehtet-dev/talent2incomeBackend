<?php

declare(strict_types=1);

namespace App\Http\Requests\Job;

use App\Models\Job;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'budget_min' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'gte:budget_min'],
            'budget_type' => ['sometimes', Rule::in(['hourly', 'fixed', 'negotiable'])],
            'deadline' => ['nullable', 'date', 'after:today'],
            'is_urgent' => ['boolean'],
            'status' => ['sometimes', Rule::in(Job::getValidStatuses())],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.max' => 'Job title cannot exceed 200 characters.',
            'description.max' => 'Job description cannot exceed 5000 characters.',
            'category_id.exists' => 'Selected category does not exist.',
            'budget_min.numeric' => 'Minimum budget must be a valid number.',
            'budget_min.min' => 'Minimum budget cannot be negative.',
            'budget_max.numeric' => 'Maximum budget must be a valid number.',
            'budget_max.min' => 'Maximum budget cannot be negative.',
            'budget_max.gte' => 'Maximum budget must be greater than or equal to minimum budget.',
            'budget_type.in' => 'Budget type must be hourly, fixed, or negotiable.',
            'deadline.date' => 'Deadline must be a valid date.',
            'deadline.after' => 'Deadline must be in the future.',
            'status.in' => 'Invalid job status.',
            'assigned_to.exists' => 'Assigned user does not exist.',
        ];
    }

    /**
     * Configure the validator instance.
     * @param mixed $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $job = $this->route('job');

            if (! $job) {
                return;
            }

            // Validate status transitions
            $newStatus = $this->input('status');
            if ($newStatus && ! $this->isValidStatusTransition($job, $newStatus)) {
                $validator->errors()->add('status', "Cannot change status from {$job->status} to {$newStatus}.");
            }

            // Validate assignment changes
            $assignedTo = $this->input('assigned_to');
            if ($assignedTo !== null && ! $this->canAssignUser($job, $assignedTo)) {
                $validator->errors()->add('assigned_to', 'Cannot assign user to this job in its current state.');
            }
        });
    }

    /**
     * Check if status transition is valid.
     */
    private function isValidStatusTransition(Job $job, string $newStatus): bool
    {
        $currentStatus = $job->status;

        // Define valid transitions
        $validTransitions = [
            Job::STATUS_OPEN => [Job::STATUS_IN_PROGRESS, Job::STATUS_CANCELLED, Job::STATUS_EXPIRED],
            Job::STATUS_IN_PROGRESS => [Job::STATUS_COMPLETED, Job::STATUS_CANCELLED],
            Job::STATUS_COMPLETED => [], // No transitions from completed
            Job::STATUS_CANCELLED => [Job::STATUS_OPEN], // Can reopen cancelled jobs
            Job::STATUS_EXPIRED => [Job::STATUS_OPEN], // Can reopen expired jobs
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }

    /**
     * Check if user can be assigned to job.
     */
    private function canAssignUser(Job $job, ?int $userId): bool
    {
        // Can only assign users to open jobs
        if ($job->status !== Job::STATUS_OPEN) {
            return false;
        }

        // Can unassign (set to null)
        if ($userId === null) {
            return true;
        }

        // Cannot assign job owner to their own job
        return $userId !== $job->user_id;
    }
}
