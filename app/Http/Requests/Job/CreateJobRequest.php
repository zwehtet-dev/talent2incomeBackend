<?php

declare(strict_types=1);

namespace App\Http\Requests\Job;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateJobRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'budget_min' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'gte:budget_min'],
            'budget_type' => ['required', Rule::in(['hourly', 'fixed', 'negotiable'])],
            'deadline' => ['nullable', 'date', 'after:today'],
            'is_urgent' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Job title is required.',
            'title.max' => 'Job title cannot exceed 200 characters.',
            'description.required' => 'Job description is required.',
            'description.max' => 'Job description cannot exceed 5000 characters.',
            'category_id.required' => 'Please select a category.',
            'category_id.exists' => 'Selected category does not exist.',
            'budget_min.numeric' => 'Minimum budget must be a valid number.',
            'budget_min.min' => 'Minimum budget cannot be negative.',
            'budget_max.numeric' => 'Maximum budget must be a valid number.',
            'budget_max.min' => 'Maximum budget cannot be negative.',
            'budget_max.gte' => 'Maximum budget must be greater than or equal to minimum budget.',
            'budget_type.required' => 'Budget type is required.',
            'budget_type.in' => 'Budget type must be hourly, fixed, or negotiable.',
            'deadline.date' => 'Deadline must be a valid date.',
            'deadline.after' => 'Deadline must be in the future.',
        ];
    }

    /**
     * Configure the validator instance.
     * @param mixed $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate budget requirements based on type
            $budgetType = $this->input('budget_type');
            $budgetMin = $this->input('budget_min');
            $budgetMax = $this->input('budget_max');

            if ($budgetType === 'fixed' && ! $budgetMin && ! $budgetMax) {
                $validator->errors()->add('budget_min', 'Fixed budget jobs require at least a minimum or maximum budget.');
            }

            if ($budgetType === 'hourly' && ! $budgetMin && ! $budgetMax) {
                $validator->errors()->add('budget_min', 'Hourly budget jobs require at least a minimum or maximum hourly rate.');
            }
        });
    }
}
