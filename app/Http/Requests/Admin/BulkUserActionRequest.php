<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUserActionRequest extends FormRequest
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
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete', 'restore', 'unlock'])],
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'integer|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'action.required' => 'An action must be specified.',
            'action.in' => 'Invalid action. Must be one of: activate, deactivate, delete, restore, unlock.',
            'user_ids.required' => 'At least one user must be selected.',
            'user_ids.array' => 'User IDs must be provided as an array.',
            'user_ids.min' => 'At least one user must be selected.',
            'user_ids.max' => 'Cannot perform bulk actions on more than 100 users at once.',
            'user_ids.*.integer' => 'All user IDs must be integers.',
            'user_ids.*.exists' => 'One or more selected users do not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'user_ids' => 'selected users',
        ];
    }
}
