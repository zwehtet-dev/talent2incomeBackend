<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RevenueAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'total_revenue',
        'platform_fees',
        'net_revenue',
        'transaction_count',
        'average_transaction_value',
    ];

    protected $casts = [
        'date' => 'date',
        'total_revenue' => 'decimal:2',
        'platform_fees' => 'decimal:2',
        'net_revenue' => 'decimal:2',
        'average_transaction_value' => 'decimal:2',
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
