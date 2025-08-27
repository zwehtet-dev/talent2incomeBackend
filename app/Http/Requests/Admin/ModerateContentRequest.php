<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateContentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->is_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content_type' => ['required', Rule::in(['review', 'job', 'skill'])],
            'content_id' => 'required|integer|min:1',
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'content_type.required' => 'Content type must be specified.',
            'content_type.in' => 'Invalid content type. Must be one of: review, job, skill.',
            'content_id.required' => 'Content ID is required.',
            'content_id.integer' => 'Content ID must be a valid integer.',
            'content_id.min' => 'Content ID must be a positive integer.',
            'action.required' => 'Moderation action must be specified.',
            'action.in' => 'Invalid action. Must be either approve or reject.',
            'reason.string' => 'Reason must be a valid string.',
            'reason.max' => 'Reason cannot exceed 500 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'content_type' => 'content type',
            'content_id' => 'content ID',
            'reason' => 'moderation reason',
        ];
    }

    /**
     * Configure the validator instance.
     * @param mixed $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $contentType = $this->input('content_type');
            $contentId = $this->input('content_id');

            if ($contentType && $contentId) {
                // Validate that the content exists and is flagged
                $exists = false;
                $isFlagged = false;

                switch ($contentType) {
                    case 'review':
                        $content = \App\Models\Review::find($contentId);
                        $exists = $content !== null;
                        $isFlagged = $content && $content->is_flagged;

                        break;

                    case 'job':
                        $content = \Illuminate\Support\Facades\DB::table('job_postings')
                            ->where('id', $contentId)
                            ->first();
                        $exists = $content !== null;
                        $isFlagged = $content && $content->is_flagged;

                        break;

                    case 'skill':
                        $content = \Illuminate\Support\Facades\DB::table('skills')
                            ->where('id', $contentId)
                            ->first();
                        $exists = $content !== null;
                        $isFlagged = $content && $content->is_flagged;

                        break;
                }

                if (! $exists) {
                    $validator->errors()->add('content_id', 'The selected content does not exist.');
                } elseif (! $isFlagged) {
                    $validator->errors()->add('content_id', 'The selected content is not flagged for moderation.');
                }
            }
        });
    }
}
