<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class ReleasePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        try {
            $payment = $this->route('payment');

            if (! $payment) {
                return false;
            }

            // Only payer can release payment, and payment must be held
            return $this->user()->id === $payment->payer_id &&
                   $payment->status === 'held';
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Payment release authorization error', [
                'error' => $e->getMessage(),
                'payment_id' => $this->route('payment')?->id,
                'user_id' => $this->user()?->id,
            ]);

            return false;
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'confirmation' => [
                'required',
                'boolean',
                'accepted',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'confirmation.required' => 'Payment release confirmation is required.',
            'confirmation.accepted' => 'You must confirm the payment release.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'confirmation' => 'release confirmation',
        ];
    }
}
