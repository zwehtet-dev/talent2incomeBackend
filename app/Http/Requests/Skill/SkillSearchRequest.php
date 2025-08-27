<?php

declare(strict_types=1);

namespace App\Http\Requests\Skill;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SkillSearchRequest extends FormRequest
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
            'search' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'pricing_type' => ['sometimes', Rule::in(['hourly', 'fixed', 'negotiable'])],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0', 'gte:min_price'],
            'location' => ['sometimes', 'string', 'max:255'],
            'is_available' => ['sometimes', 'boolean'],
            'sort_by' => ['sometimes', Rule::in(['created_at', 'price', 'rating', 'relevance'])],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
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
            'pricing_type.in' => 'Pricing type must be hourly, fixed, or negotiable.',
            'min_price.numeric' => 'Minimum price must be a valid number.',
            'min_price.min' => 'Minimum price cannot be negative.',
            'max_price.numeric' => 'Maximum price must be a valid number.',
            'max_price.min' => 'Maximum price cannot be negative.',
            'max_price.gte' => 'Maximum price must be greater than or equal to minimum price.',
            'location.max' => 'Location cannot exceed 255 characters.',
            'sort_by.in' => 'Sort by must be one of: created_at, price, rating, relevance.',
            'sort_direction.in' => 'Sort direction must be asc or desc.',
            'page.min' => 'Page must be at least 1.',
            'per_page.min' => 'Per page must be at least 1.',
            'per_page.max' => 'Per page cannot exceed 50.',
        ];
    }

    /**
     * Get the search parameters as an array.
     */
    public function getSearchParams(): array
    {
        return $this->only([
            'search',
            'category_id',
            'pricing_type',
            'min_price',
            'max_price',
            'location',
            'is_available',
            'sort_by',
            'sort_direction',
            'page',
            'per_page',
        ]);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 15),
            'sort_by' => $this->input('sort_by', 'created_at'),
            'sort_direction' => $this->input('sort_direction', 'desc'),
            'is_available' => $this->has('is_available') ? $this->boolean('is_available') : true,
        ]);

        // Clean search term
        if ($this->has('search')) {
            $this->merge(['search' => trim($this->input('search'))]);
        }
    }
}
