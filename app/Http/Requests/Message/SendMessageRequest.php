<?php

declare(strict_types=1);

namespace App\Http\Requests\Message;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recipient_id' => [
                'required',
                'integer',
                'exists:users,id',
                'different:' . $this->user()->id,
                function ($attribute, $value, $fail) {
                    $recipient = User::find($value);
                    if (! $recipient) {
                        $fail('The selected recipient does not exist.');

                        return;
                    }
                    if (! $recipient->is_active) {
                        $fail('The selected recipient is not available.');
                    }
                },
            ],
            'job_id' => [
                'nullable',
                'integer',
                'exists:jobs,id',
            ],
            'content' => [
                'required',
                'string',
                'min:1',
                'max:2000',
                function ($attribute, $value, $fail) {
                    // Basic content validation - no excessive whitespace
                    if (trim($value) === '') {
                        $fail('The message content cannot be empty.');
                    }

                    // Check for spam patterns (basic implementation)
                    if (preg_match('/(.)\1{10,}/', $value)) {
                        $fail('The message content appears to be spam.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipient_id.required' => 'A recipient is required.',
            'recipient_id.exists' => 'The selected recipient does not exist.',
            'recipient_id.different' => 'You cannot send a message to yourself.',
            'job_id.exists' => 'The selected job does not exist.',
            'content.required' => 'Message content is required.',
            'content.min' => 'Message content cannot be empty.',
            'content.max' => 'Message content cannot exceed 2000 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from content
        if ($this->has('content') && $this->input('content') !== null) {
            $this->merge([
                'content' => trim($this->input('content')),
            ]);
        }
    }
}
