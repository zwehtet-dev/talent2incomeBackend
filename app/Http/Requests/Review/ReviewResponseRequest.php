<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class ReviewResponseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $review = $this->route('review');

        return $review && $this->user()->can('respond', $review);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'response' => [
                'required',
                'string',
                'max:500',
                'min:10',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'response.required' => 'A response is required.',
            'response.min' => 'Response must be at least 10 characters long.',
            'response.max' => 'Response cannot exceed 500 characters.',
        ];
    }
}
