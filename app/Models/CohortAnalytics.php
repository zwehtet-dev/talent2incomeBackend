<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CohortAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'cohort_month',
        'period_number',
        'users_count',
        'retention_rate',
        'revenue_per_user',
    ];

    protected $casts = [
        'cohort_month' => 'date',
        'retention_rate' => 'decimal:2',
        'revenue_per_user' => 'decimal:2',
    ];

    public function scopeForCohort($query, Carbon $cohortMonth)
    {
        return $query->where('cohort_month', $cohortMonth);
    }

    public function scopeForPeriod($query, int $periodNumber)
    {
        return $query->where('period_number', $periodNumber);
    }
}
