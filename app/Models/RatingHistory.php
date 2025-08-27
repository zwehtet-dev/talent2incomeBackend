<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatingHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rating_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'simple_average',
        'weighted_average',
        'time_weighted_average',
        'decayed_rating',
        'quality_score',
        'total_reviews',
        'rating_distribution',
        'trend_data',
        'calculation_trigger',
    ];

    /**
     * The user this rating history belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by user.
     * @param mixed $query
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by date range.
     * @param mixed $query
     * @param mixed $startDate
     * @param mixed $endDate
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to order by most recent.
     * @param mixed $query
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Get the rating change from the previous entry.
     */
    public function getRatingChangeAttribute(): ?float
    {
        $previous = static::where('user_id', $this->user_id)
            ->where('created_at', '<', $this->created_at)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $previous) {
            return null;
        }

        return round($this->weighted_average - $previous->weighted_average, 2);
    }

    /**
     * Get the quality score change from the previous entry.
     */
    public function getQualityScoreChangeAttribute(): ?float
    {
        $previous = static::where('user_id', $this->user_id)
            ->where('created_at', '<', $this->created_at)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $previous) {
            return null;
        }

        return round($this->quality_score - $previous->quality_score, 2);
    }

    /**
     * Check if this is a significant rating change.
     */
    public function isSignificantChange(float $threshold = 0.2): bool
    {
        $change = $this->rating_change;

        return $change !== null && abs($change) >= $threshold;
    }

    /**
     * Get trend direction based on recent history.
     */
    public function getTrendDirection(): string
    {
        $recentEntries = static::where('user_id', $this->user_id)
            ->where('created_at', '<=', $this->created_at)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        if ($recentEntries->count() < 2) {
            return 'stable';
        }

        $changes = [];
        for ($i = 1; $i < $recentEntries->count(); $i++) {
            $changes[] = $recentEntries[$i - 1]->weighted_average - $recentEntries[$i]->weighted_average;
        }

        $averageChange = array_sum($changes) / count($changes);

        if ($averageChange > 0.1) {
            return 'improving';
        } elseif ($averageChange < -0.1) {
            return 'declining';
        }

        return 'stable';
    }

    /**
     * Create a new rating history entry from rating stats.
     */
    public static function createFromStats(int $userId, array $stats, string $trigger = 'manual'): self
    {
        return static::create([
            'user_id' => $userId,
            'simple_average' => $stats['simple_average'],
            'weighted_average' => $stats['weighted_average'],
            'time_weighted_average' => $stats['time_weighted_average'],
            'decayed_rating' => $stats['decayed_rating'],
            'quality_score' => $stats['quality_score'],
            'total_reviews' => $stats['total_reviews'],
            'rating_distribution' => $stats['rating_distribution'],
            'trend_data' => $stats['trend'],
            'calculation_trigger' => $trigger,
        ]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'simple_average' => 'decimal:2',
            'weighted_average' => 'decimal:2',
            'time_weighted_average' => 'decimal:2',
            'decayed_rating' => 'decimal:2',
            'quality_score' => 'decimal:2',
            'total_reviews' => 'integer',
            'rating_distribution' => 'array',
            'trend_data' => 'array',
        ];
    }
}
