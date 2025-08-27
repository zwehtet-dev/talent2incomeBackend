<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Skill extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Searchable;
    use \App\Traits\CacheInvalidation;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'description',
        'price_per_hour',
        'price_fixed',
        'pricing_type',
        'is_available',
        'is_active',
    ];

    /**
     * The user who offers this skill.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The category this skill belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Jobs related to this skill.
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'category_id', 'category_id');
    }

    /**
     * Get the display price based on pricing type.
     */
    public function getDisplayPriceAttribute(): string
    {
        return match ($this->pricing_type) {
            'hourly' => '$' . number_format((float) $this->price_per_hour, 2) . '/hr',
            'fixed' => '$' . number_format((float) $this->price_fixed, 2),
            'negotiable' => 'Negotiable',
            default => 'Price not set',
        };
    }

    /**
     * Get the minimum price for this skill.
     */
    public function getMinPriceAttribute(): ?float
    {
        return match ($this->pricing_type) {
            'hourly' => $this->price_per_hour ? (float) $this->price_per_hour : null,
            'fixed' => $this->price_fixed ? (float) $this->price_fixed : null,
            default => null,
        };
    }

    /**
     * Calculate estimated cost for given hours.
     */
    public function calculateCost(float $hours = 1): ?float
    {
        return match ($this->pricing_type) {
            'hourly' => $this->price_per_hour * $hours,
            'fixed' => $this->price_fixed,
            default => null,
        };
    }

    /**
     * Check if skill is within budget range.
     */
    public function isWithinBudget(float $minBudget, float $maxBudget): bool
    {
        $price = $this->min_price;

        if ($price === null) {
            return true; // Negotiable pricing
        }

        return $price >= $minBudget && $price <= $maxBudget;
    }

    /**
     * Scope to filter active skills.
     * @param mixed $query
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter available skills.
     * @param mixed $query
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope to filter by pricing type.
     * @param mixed $query
     */
    public function scopeByPricingType($query, string $type)
    {
        return $query->where('pricing_type', $type);
    }

    /**
     * Scope to filter by price range.
     * @param mixed $query
     */
    public function scopeInPriceRange($query, ?float $minPrice = null, ?float $maxPrice = null)
    {
        return $query->where(function ($q) use ($minPrice, $maxPrice) {
            if ($minPrice !== null && $maxPrice !== null) {
                $q->where(function ($subQ) use ($minPrice, $maxPrice) {
                    $subQ->where('pricing_type', 'hourly')
                        ->whereBetween('price_per_hour', [$minPrice, $maxPrice]);
                })->orWhere(function ($subQ) use ($minPrice, $maxPrice) {
                    $subQ->where('pricing_type', 'fixed')
                        ->whereBetween('price_fixed', [$minPrice, $maxPrice]);
                })->orWhere('pricing_type', 'negotiable');
            } elseif ($minPrice !== null) {
                $q->where(function ($subQ) use ($minPrice) {
                    $subQ->where('pricing_type', 'hourly')
                        ->where('price_per_hour', '>=', $minPrice);
                })->orWhere(function ($subQ) use ($minPrice) {
                    $subQ->where('pricing_type', 'fixed')
                        ->where('price_fixed', '>=', $minPrice);
                })->orWhere('pricing_type', 'negotiable');
            } elseif ($maxPrice !== null) {
                $q->where(function ($subQ) use ($maxPrice) {
                    $subQ->where('pricing_type', 'hourly')
                        ->where('price_per_hour', '<=', $maxPrice);
                })->orWhere(function ($subQ) use ($maxPrice) {
                    $subQ->where('pricing_type', 'fixed')
                        ->where('price_fixed', '<=', $maxPrice);
                })->orWhere('pricing_type', 'negotiable');
            }
        });
    }

    /**
     * Scope for full-text search.
     * @param mixed $query
     */
    public function scopeSearch($query, string $term)
    {
        // Check if we're using MySQL for full-text search
        if (config('database.default') === 'mysql') {
            return $query->whereRaw(
                'MATCH(title, description) AGAINST(? IN NATURAL LANGUAGE MODE)',
                [$term]
            )->orWhere('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        }

        // Fallback to LIKE search for other databases (SQLite, PostgreSQL)
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /**
     * Scope to order by relevance for search.
     * @param mixed $query
     */
    public function scopeOrderByRelevance($query, string $term)
    {
        // Check if we're using MySQL for full-text search
        if (config('database.default') === 'mysql') {
            return $query->orderByRaw(
                'MATCH(title, description) AGAINST(? IN NATURAL LANGUAGE MODE) DESC',
                [$term]
            );
        }

        // Fallback ordering for other databases
        return $query->orderByRaw(
            'CASE 
                WHEN title LIKE ? THEN 1 
                WHEN description LIKE ? THEN 2 
                ELSE 3 
            END',
            ["%{$term}%", "%{$term}%"]
        );
    }

    /**
     * Scope to filter by category.
     * @param mixed $query
     */
    public function scopeInCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope to filter by user location.
     * @param mixed $query
     */
    public function scopeNearLocation($query, string $location)
    {
        return $query->whereHas('user', function ($q) use ($location) {
            $q->where('location', 'like', "%{$location}%");
        });
    }

    /**
     * Scope to include user and category relationships.
     * @param mixed $query
     */
    public function scopeWithRelations($query)
    {
        return $query->with(['user:id,first_name,last_name,avatar,location', 'category:id,name,slug']);
    }

    /**
     * Scope to order by user rating.
     * @param mixed $query
     */
    public function scopeOrderByUserRating($query, string $direction = 'desc')
    {
        return $query->join('users', 'skills.user_id', '=', 'users.id')
            ->leftJoin('reviews', 'users.id', '=', 'reviews.reviewee_id')
            ->groupBy('skills.id')
            ->orderByRaw("AVG(reviews.rating) {$direction}");
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        $array = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'pricing_type' => $this->pricing_type,
            'price_per_hour' => $this->price_per_hour,
            'price_fixed' => $this->price_fixed,
            'is_active' => $this->is_active,
            'is_available' => $this->is_available,
            'category_id' => $this->category_id,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at?->timestamp,
            'updated_at' => $this->updated_at?->timestamp,
        ];

        // Add related data for advanced search engines (Algolia, Meilisearch)
        if (config('scout.driver') !== 'database') {
            $this->loadMissing(['user', 'category']);

            if ($this->user) {
                $array['user'] = [
                    'id' => $this->user->id,
                    'first_name' => $this->user->first_name,
                    'last_name' => $this->user->last_name,
                    'location' => $this->user->location,
                    'rating_cache_average' => $this->user->rating_cache_average ?? 0,
                    'rating_range' => $this->getRatingRange($this->user->rating_cache_average ?? 0),
                ];
            }

            if ($this->category) {
                $array['category'] = [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }

            // Add computed fields for better ranking
            $array['min_price'] = $this->min_price;
            $array['display_price'] = $this->display_price;
        }

        return $array;
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->is_active && $this->is_available && ! $this->trashed();
    }

    /**
     * Get the Scout search index name.
     */
    public function searchableAs(): string
    {
        return 'skills_index';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_per_hour' => 'decimal:2',
            'price_fixed' => 'decimal:2',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Modify the query used to retrieve models when making all of the models searchable.
     * @param mixed $query
     */
    protected function makeAllSearchableUsing($query)
    {
        return $query->with(['user:id,first_name,last_name,location,rating_cache_average', 'category:id,name']);
    }

    /**
     * Get rating range for faceting.
     */
    private function getRatingRange(float $rating): string
    {
        if ($rating >= 4.5) {
            return '4.5+';
        }
        if ($rating >= 4.0) {
            return '4.0-4.5';
        }
        if ($rating >= 3.5) {
            return '3.5-4.0';
        }
        if ($rating >= 3.0) {
            return '3.0-3.5';
        }

        return 'below-3.0';
    }
}
