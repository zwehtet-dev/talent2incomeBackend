<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|string|max:100|regex:/^[a-zA-Z\s\-\'\.]+$/',
            'last_name' => 'sometimes|string|max:100|regex:/^[a-zA-Z\s\-\'\.]+$/',
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->user()->id),
            ],
            'bio' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20|regex:/^[\+]?[0-9\s\-\(\)]+$/',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'first_name.regex' => 'The first name may only contain letters, spaces, hyphens, apostrophes, and periods.',
            'last_name.regex' => 'The last name may only contain letters, spaces, hyphens, apostrophes, and periods.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already taken.',
            'bio.max' => 'The bio may not be longer than 1000 characters.',
            'phone.regex' => 'Please provide a valid phone number.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Only process fields that are actually present in the request
        $data = [];

        if ($this->has('first_name')) {
            $data['first_name'] = trim($this->first_name);
        }

        if ($this->has('last_name')) {
            $data['last_name'] = trim($this->last_name);
        }

        if ($this->has('email')) {
            $data['email'] = strtolower(trim($this->email));
        }

        if ($this->has('bio')) {
            $data['bio'] = $this->bio ? trim($this->bio) : null;
        }

        if ($this->has('location')) {
            $data['location'] = $this->location ? trim($this->location) : null;
        }

        if ($this->has('phone')) {
            $data['phone'] = $this->phone ? trim($this->phone) : null;
        }

        $this->merge($data);
    }
}
