<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use App\Models\Job;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        try {
            $job = Job::find($this->input('job_id'));

            if (! $job) {
                return false;
            }

            // Only job owner can create payment, job must be completed with assigned user
            return $this->user()->id === $job->user_id &&
                   $job->status === Job::STATUS_COMPLETED &&
                   $job->assigned_to !== null;
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Payment authorization error', [
                'error' => $e->getMessage(),
                'job_id' => $this->input('job_id'),
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
            'job_id' => [
                'required',
                'integer',
                'exists:job_postings,id',
                function ($attribute, $value, $fail) {
                    $job = Job::find($value);
                    if (! $job) {
                        $fail('The selected job does not exist.');

                        return;
                    }

                    if ($job->status !== Job::STATUS_COMPLETED) {
                        $fail('Payment can only be created for completed jobs.');

                        return;
                    }

                    if (! $job->assigned_to) {
                        $fail('Job must have an assigned user to create payment.');

                        return;
                    }

                    // Check if payment already exists for this job
                    $existingPayment = Payment::where('job_id', $value)->first();
                    if ($existingPayment) {
                        $fail('Payment already exists for this job.');
                    }
                },
            ],
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'max:999999.99',
                function ($attribute, $value, $fail) {
                    $job = Job::find($this->input('job_id'));
                    if ($job && $job->budget_max && $value > $job->budget_max * 1.1) {
                        $fail('Payment amount cannot exceed 110% of job budget.');
                    }
                },
            ],
            'payment_method' => [
                'required',
                'string',
                'max:50',
                Rule::in(['stripe', 'paypal', 'bank_transfer', 'credit_card', 'debit_card']),
            ],
            'payment_method_token' => [
                'nullable',
                'string',
                'max:255',
            ],
            'description' => [
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
            'job_id.required' => 'Job ID is required.',
            'job_id.exists' => 'The selected job does not exist.',
            'amount.required' => 'Payment amount is required.',
            'amount.numeric' => 'Payment amount must be a valid number.',
            'amount.min' => 'Payment amount must be at least $1.00.',
            'amount.max' => 'Payment amount cannot exceed $999,999.99.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'Invalid payment method selected.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'job_id' => 'job',
            'payment_method' => 'payment method',
            'payment_method_token' => 'payment method token',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure amount is properly formatted
        if ($this->has('amount')) {
            $this->merge([
                'amount' => round((float) $this->input('amount'), 2),
            ]);
        }
    }
}
