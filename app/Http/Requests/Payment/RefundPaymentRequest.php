<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundPaymentRequest extends FormRequest
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

            // Only payer can request refund, payment must be held or recently released
            $canRefund = $this->user()->id === $payment->payer_id &&
                        in_array($payment->status, ['held', 'released']);

            // If payment is released, check if it's within dispute window (7 days)
            if ($payment->status === 'released') {
                $canRefund = $canRefund && $payment->updated_at->diffInDays(now()) <= 7;
            }

            return $canRefund;
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Payment refund authorization error', [
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
            'reason' => [
                'required',
                'string',
                Rule::in([
                    'work_not_completed',
                    'poor_quality',
                    'not_as_described',
                    'communication_issues',
                    'deadline_missed',
                    'other',
                ]),
            ],
            'description' => [
                'required',
                'string',
                'min:10',
                'max:1000',
            ],
            'evidence' => [
                'nullable',
                'array',
                'max:5',
            ],
            'evidence.*' => [
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Refund reason is required.',
            'reason.in' => 'Invalid refund reason selected.',
            'description.required' => 'Detailed description is required.',
            'description.min' => 'Description must be at least 10 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'evidence.max' => 'You can upload a maximum of 5 evidence files.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'reason' => 'refund reason',
            'description' => 'detailed description',
            'evidence' => 'evidence files',
        ];
    }
}
