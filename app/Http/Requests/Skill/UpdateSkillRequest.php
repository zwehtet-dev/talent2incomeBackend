<?php

declare(strict_types=1);

namespace App\Http\Requests\Skill;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSkillRequest extends FormRequest
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
            'description' => ['sometimes', 'string', 'max:2000'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'price_per_hour' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'price_fixed' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'pricing_type' => ['sometimes', Rule::in(['hourly', 'fixed', 'negotiable'])],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     * @param mixed $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $skill = $this->route('skill');
            $pricingType = $this->input('pricing_type', $skill->pricing_type);
            $pricePerHour = $this->input('price_per_hour');
            $priceFixed = $this->input('price_fixed');

            // Only validate pricing if pricing_type is being updated or prices are being set
            if ($this->has('pricing_type') || $this->has('price_per_hour') || $this->has('price_fixed')) {
                // Validate pricing based on type
                if ($pricingType === 'hourly') {
                    if ($this->has('price_per_hour') && empty($pricePerHour)) {
                        $validator->errors()->add('price_per_hour', 'Price per hour is required for hourly pricing.');
                    }
                    if ($this->has('price_fixed') && ! empty($priceFixed)) {
                        $validator->errors()->add('price_fixed', 'Fixed price should not be set for hourly pricing.');
                    }
                }

                if ($pricingType === 'fixed') {
                    if ($this->has('price_fixed') && empty($priceFixed)) {
                        $validator->errors()->add('price_fixed', 'Fixed price is required for fixed pricing.');
                    }
                    if ($this->has('price_per_hour') && ! empty($pricePerHour)) {
                        $validator->errors()->add('price_per_hour', 'Hourly price should not be set for fixed pricing.');
                    }
                }

                if ($pricingType === 'negotiable') {
                    if ((! empty($pricePerHour) && $this->has('price_per_hour')) ||
                        (! empty($priceFixed) && $this->has('price_fixed'))) {
                        $validator->errors()->add('pricing_type', 'No prices should be set for negotiable pricing.');
                    }
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.max' => 'Skill title cannot exceed 200 characters.',
            'description.max' => 'Skill description cannot exceed 2000 characters.',
            'category_id.exists' => 'Selected category does not exist.',
            'price_per_hour.numeric' => 'Hourly price must be a valid number.',
            'price_per_hour.min' => 'Hourly price cannot be negative.',
            'price_per_hour.max' => 'Hourly price cannot exceed $9,999.99.',
            'price_fixed.numeric' => 'Fixed price must be a valid number.',
            'price_fixed.min' => 'Fixed price cannot be negative.',
            'price_fixed.max' => 'Fixed price cannot exceed $999,999.99.',
            'pricing_type.in' => 'Pricing type must be hourly, fixed, or negotiable.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $skill = $this->route('skill');
        $pricingType = $this->input('pricing_type', $skill->pricing_type);

        // Clean up pricing fields based on type if pricing_type is being updated
        if ($this->has('pricing_type')) {
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
}
