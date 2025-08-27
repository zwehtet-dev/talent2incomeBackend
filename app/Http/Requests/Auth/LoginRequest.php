<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     * @param mixed $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->hasTooManyLoginAttempts()) {
                $this->fireLockoutEvent();

                throw ValidationException::withMessages([
                    'email' => [trans('auth.throttle', [
                        'seconds' => $this->availableIn(),
                    ])],
                ]);
            }
        });
    }

    /**
     * Determine if the user has too many failed login attempts.
     */
    public function hasTooManyLoginAttempts(): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->throttleKey(),
            5 // max attempts
        );
    }

    /**
     * Increment the login attempts for the user.
     */
    public function incrementLoginAttempts(): void
    {
        RateLimiter::hit(
            $this->throttleKey(),
            900 // 15 minutes
        );
    }

    /**
     * Clear the login locks for the given user credentials.
     */
    public function clearLoginAttempts(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Fire an event when a lockout occurs.
     */
    public function fireLockoutEvent(): void
    {
        event(new \Illuminate\Auth\Events\Lockout($this));
    }

    /**
     * Get the number of seconds until the next retry.
     */
    public function availableIn(): int
    {
        return RateLimiter::availableIn($this->throttleKey());
    }

    /**
     * Get the throttle key for the given request.
     */
    public function throttleKey(): string
    {
        return strtolower($this->input('email')) . '|' . $this->ip();
    }
}
