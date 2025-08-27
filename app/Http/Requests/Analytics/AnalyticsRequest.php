<?php

namespace App\Http\Requests\Analytics;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class AnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view-analytics');
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date|before_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date|before_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'Start date is required',
            'start_date.date' => 'Start date must be a valid date',
            'start_date.before_or_equal' => 'Start date cannot be in the future',
            'end_date.required' => 'End date is required',
            'end_date.date' => 'End date must be a valid date',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
            'end_date.before_or_equal' => 'End date cannot be in the future',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $startDate = Carbon::parse($this->start_date);
            $endDate = Carbon::parse($this->end_date);

            // Limit date range to maximum 1 year
            if ($startDate->diffInDays($endDate) > 365) {
                $validator->errors()->add('date_range', 'Date range cannot exceed 365 days');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        // Set default dates if not provided
        if (! $this->has('start_date')) {
            $this->merge(['start_date' => now()->subDays(30)->format('Y-m-d')]);
        }

        if (! $this->has('end_date')) {
            $this->merge(['end_date' => now()->format('Y-m-d')]);
        }
    }
}
