<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserEngagementAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'daily_active_users',
        'weekly_active_users',
        'monthly_active_users',
        'new_registrations',
        'jobs_posted',
        'skills_posted',
        'messages_sent',
        'reviews_created',
        'average_session_duration',
    ];

    protected $casts = [
        'date' => 'date',
        'average_session_duration' => 'decimal:2',
    ];

    public function scopeForDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->whereYear('date', $year);
    }
}
