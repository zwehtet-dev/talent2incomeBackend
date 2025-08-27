<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
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
            'avatar' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,gif,webp',
                'max:5120', // 5MB max
                'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'Please select an image file to upload.',
            'avatar.image' => 'The uploaded file must be an image.',
            'avatar.mimes' => 'The avatar must be a file of type: jpeg, jpg, png, gif, webp.',
            'avatar.max' => 'The avatar may not be larger than 5MB.',
            'avatar.dimensions' => 'The avatar must be at least 100x100 pixels and no larger than 2000x2000 pixels.',
        ];
    }
}
