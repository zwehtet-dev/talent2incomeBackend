<?php

declare(strict_types=1);

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class MessageSearchRequest extends FormRequest
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
            'query' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'job_id' => [
                'nullable',
                'integer',
                'exists:jobs,id',
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
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
            'query.required' => 'Search query is required.',
            'query.min' => 'Search query must be at least 2 characters.',
            'query.max' => 'Search query cannot exceed 100 characters.',
            'user_id.exists' => 'The selected user does not exist.',
            'job_id.exists' => 'The selected job does not exist.',
            'per_page.max' => 'Cannot retrieve more than 50 messages per page.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 20),
        ]);

        // Trim search query
        if ($this->has('query')) {
            $this->merge([
                'query' => trim($this->input('query')),
            ]);
        }
    }
}
