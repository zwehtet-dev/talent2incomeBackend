<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $review = $this->route('review');

        return $review && $this->user()->can('update', $review);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'rating' => [
                'sometimes',
                'integer',
                'min:' . Review::MIN_RATING,
                'max:' . Review::MAX_RATING,
            ],
            'comment' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
                'min:10',
            ],
            'is_public' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'rating.min' => 'Rating must be at least ' . Review::MIN_RATING . ' star.',
            'rating.max' => 'Rating cannot exceed ' . Review::MAX_RATING . ' stars.',
            'comment.min' => 'Comment must be at least 10 characters long.',
            'comment.max' => 'Comment cannot exceed 1000 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     * @param mixed $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $review = $this->route('review');

            if ($review && ! $review->canBeEdited()) {
                $validator->errors()->add('review', 'This review can no longer be edited.');
            }
        });
    }
}
