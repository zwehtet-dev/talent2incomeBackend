<?php

declare(strict_types=1);

namespace App\Http\Requests\Message;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class BlockUserRequest extends FormRequest
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
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ((int) $value === $this->user()->id) {
                        $fail('You cannot block yourself.');

                        return;
                    }

                    $user = User::find($value);
                    if ($user && $user->is_admin) {
                        $fail('You cannot block an administrator.');
                    }
                },
            ],
            'reason' => [
                'nullable',
                'string',
                'max:500',
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
            'user_id.required' => 'User ID is required.',
            'user_id.exists' => 'The selected user does not exist.',
            'user_id.different' => 'You cannot block yourself.',
            'reason.max' => 'Block reason cannot exceed 500 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim reason if provided
        if ($this->has('reason')) {
            $this->merge([
                'reason' => trim($this->input('reason')),
            ]);
        }
    }
}
