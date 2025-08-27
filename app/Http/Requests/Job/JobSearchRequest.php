<?php

declare(strict_types=1);

namespace App\Http\Requests\Job;

use App\Models\Job;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'gte:budget_min'],
            'budget_type' => ['nullable', Rule::in(['hourly', 'fixed', 'negotiable'])],
            'status' => ['nullable', Rule::in(Job::getValidStatuses())],
            'is_urgent' => ['nullable', 'boolean'],
            'deadline_from' => ['nullable', 'date'],
            'deadline_to' => ['nullable', 'date', 'after_or_equal:deadline_from'],
            'location' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', Rule::in(['created_at', 'deadline', 'budget', 'relevance'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Search term cannot exceed 255 characters.',
            'category_id.exists' => 'Selected category does not exist.',
            'budget_min.numeric' => 'Minimum budget must be a valid number.',
            'budget_min.min' => 'Minimum budget cannot be negative.',
            'budget_max.numeric' => 'Maximum budget must be a valid number.',
            'budget_max.min' => 'Maximum budget cannot be negative.',
            'budget_max.gte' => 'Maximum budget must be greater than or equal to minimum budget.',
            'budget_type.in' => 'Budget type must be hourly, fixed, or negotiable.',
            'status.in' => 'Invalid job status.',
            'deadline_from.date' => 'Deadline from must be a valid date.',
            'deadline_to.date' => 'Deadline to must be a valid date.',
            'deadline_to.after_or_equal' => 'Deadline to must be after or equal to deadline from.',
            'location.max' => 'Location cannot exceed 255 characters.',
            'sort_by.in' => 'Sort by must be one of: created_at, deadline, budget, relevance.',
            'sort_direction.in' => 'Sort direction must be asc or desc.',
            'page.min' => 'Page must be at least 1.',
            'per_page.min' => 'Per page must be at least 1.',
            'per_page.max' => 'Per page cannot exceed 50.',
        ];
    }

    /**
     * Configure the validator instance.
     * @param mixed $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If sorting by relevance, search term is required
            if ($this->input('sort_by') === 'relevance' && ! $this->input('search')) {
                $validator->errors()->add('search', 'Search term is required when sorting by relevance.');
            }
        });
    }

    /**
     * Get validated data with defaults.
     */
    public function getValidatedWithDefaults(): array
    {
        $validated = $this->validated();

        return array_merge([
            'page' => 1,
            'per_page' => 15,
            'sort_by' => 'created_at',
            'sort_direction' => 'desc',
        ], $validated);
    }
}
