<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public endpoint with filtering
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'sometimes',
                'integer',
                'exists:users,id',
            ],
            'job_id' => [
                'sometimes',
                'integer',
                'exists:jobs,id',
            ],
            'rating' => [
                'sometimes',
                'integer',
                'min:' . Review::MIN_RATING,
                'max:' . Review::MAX_RATING,
            ],
            'rating_min' => [
                'sometimes',
                'integer',
                'min:' . Review::MIN_RATING,
                'max:' . Review::MAX_RATING,
            ],
            'rating_max' => [
                'sometimes',
                'integer',
                'min:' . Review::MIN_RATING,
                'max:' . Review::MAX_RATING,
                'gte:rating_min',
            ],
            'is_public' => [
                'sometimes',
                'in:true,false,1,0',
            ],
            'is_flagged' => [
                'sometimes',
                'in:true,false,1,0',
            ],
            'search' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'sort_by' => [
                'sometimes',
                'string',
                Rule::in(['created_at', 'rating', 'updated_at']),
            ],
            'sort_direction' => [
                'sometimes',
                'string',
                Rule::in(['asc', 'desc']),
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
            'recent_days' => [
                'sometimes',
                'integer',
                'min:1',
                'max:365',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'The selected user does not exist.',
            'job_id.exists' => 'The selected job does not exist.',
            'rating.min' => 'Rating must be at least ' . Review::MIN_RATING . '.',
            'rating.max' => 'Rating cannot exceed ' . Review::MAX_RATING . '.',
            'rating_min.min' => 'Minimum rating must be at least ' . Review::MIN_RATING . '.',
            'rating_min.max' => 'Minimum rating cannot exceed ' . Review::MAX_RATING . '.',
            'rating_max.min' => 'Maximum rating must be at least ' . Review::MIN_RATING . '.',
            'rating_max.max' => 'Maximum rating cannot exceed ' . Review::MAX_RATING . '.',
            'rating_max.gte' => 'Maximum rating must be greater than or equal to minimum rating.',
            'sort_by.in' => 'Sort by must be one of: created_at, rating, updated_at.',
            'sort_direction.in' => 'Sort direction must be either asc or desc.',
            'per_page.min' => 'Per page must be at least 1.',
            'per_page.max' => 'Per page cannot exceed 100.',
            'recent_days.min' => 'Recent days must be at least 1.',
            'recent_days.max' => 'Recent days cannot exceed 365.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'per_page' => $this->input('per_page', 15),
            'page' => $this->input('page', 1),
            'sort_by' => $this->input('sort_by', 'created_at'),
            'sort_direction' => $this->input('sort_direction', 'desc'),
        ]);
    }
}
