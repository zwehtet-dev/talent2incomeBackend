<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'recipients',
        'metrics',
        'frequency',
        'last_sent_at',
        'next_send_at',
        'is_active',
    ];

    protected $casts = [
        'recipients' => 'array',
        'metrics' => 'array',
        'last_sent_at' => 'datetime',
        'next_send_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDue($query)
    {
        return $query->where('next_send_at', '<=', now());
    }

    public function generatedReports()
    {
        return $this->hasMany(GeneratedReport::class, 'name', 'name');
    }
}
