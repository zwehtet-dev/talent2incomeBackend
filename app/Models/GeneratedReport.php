<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneratedReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'report_date',
        'data',
        'file_path',
        'generated_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'data' => 'array',
        'generated_at' => 'datetime',
    ];

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('report_date', [$startDate, $endDate]);
    }

    public function scheduledReport()
    {
        return $this->belongsTo(ScheduledReport::class, 'name', 'name');
    }
}
