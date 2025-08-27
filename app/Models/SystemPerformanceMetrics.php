<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemPerformanceMetrics extends Model
{
    use HasFactory;

    protected $fillable = [
        'recorded_at',
        'average_response_time',
        'total_requests',
        'error_count',
        'error_rate',
        'cpu_usage',
        'memory_usage',
        'disk_usage',
        'active_connections',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'average_response_time' => 'decimal:2',
        'error_rate' => 'decimal:2',
        'cpu_usage' => 'decimal:2',
        'memory_usage' => 'decimal:2',
        'disk_usage' => 'decimal:2',
    ];

    public function scopeForDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    public function scopeLatest($query, int $hours = 24)
    {
        return $query->where('recorded_at', '>=', now()->subHours($hours));
    }
}
