<?php

declare(strict_types=1);

namespace App\Http\Requests\Skill;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSkillRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:2000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'price_per_hour' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'price_fixed' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'pricing_type' => ['required', Rule::in(['hourly', 'fixed', 'negotiable'])],
            'is_available' => ['boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     * @param mixed $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $pricingType = $this->input('pricing_type');
            $pricePerHour = $this->input('price_per_hour');
            $priceFixed = $this->input('price_fixed');

            // Validate pricing based on type
            if ($pricingType === 'hourly' && empty($pricePerHour)) {
                $validator->errors()->add('price_per_hour', 'Price per hour is required for hourly pricing.');
            }

            if ($pricingType === 'fixed' && empty($priceFixed)) {
                $validator->errors()->add('price_fixed', 'Fixed price is required for fixed pricing.');
            }

            // Ensure only relevant price field is set
            if ($pricingType === 'hourly' && ! empty($priceFixed)) {
                $validator->errors()->add('price_fixed', 'Fixed price should not be set for hourly pricing.');
            }

            if ($pricingType === 'fixed' && ! empty($pricePerHour)) {
                $validator->errors()->add('price_per_hour', 'Hourly price should not be set for fixed pricing.');
            }

            if ($pricingType === 'negotiable' && (! empty($pricePerHour) || ! empty($priceFixed))) {
                $validator->errors()->add('pricing_type', 'No prices should be set for negotiable pricing.');
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Skill title is required.',
            'title.max' => 'Skill title cannot exceed 200 characters.',
            'description.required' => 'Skill description is required.',
            'description.max' => 'Skill description cannot exceed 2000 characters.',
            'category_id.required' => 'Category is required.',
            'category_id.exists' => 'Selected category does not exist.',
            'price_per_hour.numeric' => 'Hourly price must be a valid number.',
            'price_per_hour.min' => 'Hourly price cannot be negative.',
            'price_per_hour.max' => 'Hourly price cannot exceed $9,999.99.',
            'price_fixed.numeric' => 'Fixed price must be a valid number.',
            'price_fixed.min' => 'Fixed price cannot be negative.',
            'price_fixed.max' => 'Fixed price cannot exceed $999,999.99.',
            'pricing_type.required' => 'Pricing type is required.',
            'pricing_type.in' => 'Pricing type must be hourly, fixed, or negotiable.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default availability to true if not provided
        if (! $this->has('is_available')) {
            $this->merge(['is_available' => true]);
        }

        // Clean up pricing fields based on type
        $pricingType = $this->input('pricing_type');

        if ($pricingType === 'hourly') {
            $this->merge(['price_fixed' => null]);
        } elseif ($pricingType === 'fixed') {
            $this->merge(['price_per_hour' => null]);
        } elseif ($pricingType === 'negotiable') {
            $this->merge([
                'price_per_hour' => null,
                'price_fixed' => null,
            ]);
        }
    }
}
