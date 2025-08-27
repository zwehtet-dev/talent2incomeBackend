<?php

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class CreateScheduledReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-analytics');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:scheduled_reports,name',
            'type' => 'required|string|in:daily,weekly,monthly',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|email|distinct',
            'metrics' => 'required|array|min:1',
            'metrics.*' => 'required|string|in:revenue_analytics,user_engagement,cohort_analysis,system_performance,key_metrics,trends,forecasting',
            'frequency' => 'required|string|in:daily,weekly,monthly',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Report name is required',
            'name.unique' => 'A scheduled report with this name already exists',
            'type.required' => 'Report type is required',
            'type.in' => 'Report type must be daily, weekly, or monthly',
            'recipients.required' => 'At least one recipient email is required',
            'recipients.min' => 'At least one recipient email is required',
            'recipients.*.email' => 'All recipients must be valid email addresses',
            'recipients.*.distinct' => 'Duplicate email addresses are not allowed',
            'metrics.required' => 'At least one metric must be selected',
            'metrics.min' => 'At least one metric must be selected',
            'metrics.*.in' => 'Invalid metric selected',
            'frequency.required' => 'Report frequency is required',
            'frequency.in' => 'Frequency must be daily, weekly, or monthly',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that type and frequency are compatible
            if ($this->type !== $this->frequency) {
                $validator->errors()->add('frequency', 'Report frequency must match the report type');
            }

            // Limit number of recipients
            if (count($this->recipients) > 10) {
                $validator->errors()->add('recipients', 'Maximum 10 recipients allowed per scheduled report');
            }

            // Validate metric combinations
            $metrics = $this->metrics ?? [];
            if (in_array('forecasting', $metrics) && ! in_array('revenue_analytics', $metrics)) {
                $validator->errors()->add('metrics', 'Forecasting requires revenue analytics to be included');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        // Set default values
        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }

        // Ensure type and frequency match
        if (! $this->has('type') && $this->has('frequency')) {
            $this->merge(['type' => $this->frequency]);
        }
    }
}
