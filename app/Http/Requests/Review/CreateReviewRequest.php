<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use App\Models\Job;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CreateReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $job = Job::find($this->input('job_id'));
        $reviewee = User::find($this->input('reviewee_id'));

        if (! $job || ! $reviewee) {
            return false;
        }

        return $this->user()->can('createForJob', [Review::class, $job]) &&
               $this->user()->can('reviewUser', [Review::class, $reviewee, $job]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'job_id' => [
                'required',
                'integer',
                'exists:jobs,id',
                function ($attribute, $value, $fail) {
                    $job = Job::find($value);
                    if ($job && $job->status !== 'completed') {
                        $fail('Reviews can only be created for completed jobs.');
                    }
                },
            ],
            'reviewee_id' => [
                'required',
                'integer',
                'exists:users,id',
                'different:' . $this->user()->id,
                function ($attribute, $value, $fail) {
                    $job = Job::find($this->input('job_id'));
                    if ($job && ! in_array($value, [$job->user_id, $job->assigned_to])) {
                        $fail('You can only review users involved in this job.');
                    }
                },
            ],
            'rating' => [
                'required',
                'integer',
                'min:' . Review::MIN_RATING,
                'max:' . Review::MAX_RATING,
            ],
            'comment' => [
                'nullable',
                'string',
                'max:1000',
                'min:10',
            ],
            'is_public' => [
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
            'job_id.required' => 'A job ID is required.',
            'job_id.exists' => 'The selected job does not exist.',
            'reviewee_id.required' => 'A reviewee ID is required.',
            'reviewee_id.exists' => 'The selected user does not exist.',
            'reviewee_id.different' => 'You cannot review yourself.',
            'rating.required' => 'A rating is required.',
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
            // Check if user has already reviewed this job
            $existingReview = Review::where('job_id', $this->input('job_id'))
                ->where('reviewer_id', $this->user()->id)
                ->exists();

            if ($existingReview) {
                $validator->errors()->add('job_id', 'You have already reviewed this job.');
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reviewer_id' => $this->user()->id,
            'is_public' => $this->input('is_public', true),
        ]);
    }
}
