<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveDisputeRequest extends FormRequest
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
            'resolution' => ['required', Rule::in(['refund_full', 'refund_partial', 'release_full', 'release_partial', 'no_action'])],
            'resolution_notes' => 'required|string|max:1000',
            'refund_amount' => 'nullable|numeric|min:0',
            'release_amount' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'resolution.required' => 'A resolution action must be specified.',
            'resolution.in' => 'Invalid resolution. Must be one of: refund_full, refund_partial, release_full, release_partial, no_action.',
            'resolution_notes.required' => 'Resolution notes are required to document the decision.',
            'resolution_notes.string' => 'Resolution notes must be a valid string.',
            'resolution_notes.max' => 'Resolution notes cannot exceed 1000 characters.',
            'refund_amount.numeric' => 'Refund amount must be a valid number.',
            'refund_amount.min' => 'Refund amount cannot be negative.',
            'release_amount.numeric' => 'Release amount must be a valid number.',
            'release_amount.min' => 'Release amount cannot be negative.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'resolution_notes' => 'resolution notes',
            'refund_amount' => 'refund amount',
            'release_amount' => 'release amount',
        ];
    }

    /**
     * Configure the validator instance.
     * @param mixed $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $resolution = $this->input('resolution');
            $refundAmount = $this->input('refund_amount');
            $releaseAmount = $this->input('release_amount');

            // Get the payment to validate amounts
            $paymentId = $this->route('payment');
            $payment = \App\Models\Payment::find($paymentId);

            if ($payment) {
                // Validate refund amount for partial refunds
                if ($resolution === 'refund_partial') {
                    if (! $refundAmount) {
                        $validator->errors()->add('refund_amount', 'Refund amount is required for partial refunds.');
                    } elseif ($refundAmount > $payment->amount) {
                        $validator->errors()->add('refund_amount', 'Refund amount cannot exceed the payment amount.');
                    }
                }

                // Validate release amount for partial releases
                if ($resolution === 'release_partial') {
                    if (! $releaseAmount) {
                        $validator->errors()->add('release_amount', 'Release amount is required for partial releases.');
                    } elseif ($releaseAmount > $payment->amount) {
                        $validator->errors()->add('release_amount', 'Release amount cannot exceed the payment amount.');
                    }
                }

                // Ensure payment is actually disputed
                if ($payment->status !== 'disputed') {
                    $validator->errors()->add('payment', 'This payment is not in disputed status.');
                }

                // Ensure dispute is not already resolved
                if ($payment->dispute_resolved_at) {
                    $validator->errors()->add('payment', 'This dispute has already been resolved.');
                }
            } else {
                $validator->errors()->add('payment', 'Payment not found.');
            }
        });
    }
}
