<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'parent_id',
        'left',
        'right',
        'depth',
        'is_active',
    ];

    /**
     * Jobs in this category.
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    /**
     * Skills in this category.
     */
    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    /**
     * Parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get all ancestors of this category.
     */
    public function ancestors()
    {
        return static::where('left', '<', $this->left)
            ->where('right', '>', $this->right)
            ->orderBy('left');
    }

    /**
     * Get all descendants of this category.
     */
    public function descendants()
    {
        return static::where('left', '>', $this->left)
            ->where('right', '<', $this->right)
            ->orderBy('left');
    }

    /**
     * Get immediate children of this category.
     */
    public function getImmediateChildren()
    {
        return static::where('parent_id', $this->id)
            ->where('is_active', true)
            ->orderBy('name');
    }

    /**
     * Check if this category is a leaf (has no children).
     */
    public function isLeaf(): bool
    {
        return $this->right - $this->left === 1;
    }

    /**
     * Check if this category is a root (has no parent).
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Get the path from root to this category.
     */
    public function getPath(): string
    {
        $ancestors = $this->ancestors()->pluck('name')->toArray();
        $ancestors[] = $this->name;

        return implode(' > ', $ancestors);
    }

    /**
     * Scope to filter active categories.
     * @param mixed $query
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get root categories.
     * @param mixed $query
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get leaf categories.
     * @param mixed $query
     */
    public function scopeLeaves($query)
    {
        return $query->whereRaw('`right` - `left` = 1');
    }

    /**
     * Scope to search categories by name.
     * @param mixed $query
     * @param mixed $term
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('name', 'like', "%{$term}%")
            ->orWhere('description', 'like', "%{$term}%");
    }

    /**
     * Get categories with job/skill counts.
     * @param mixed $query
     */
    public function scopeWithCounts($query)
    {
        return $query->withCount(['jobs', 'skills']);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'left' => 'integer',
            'right' => 'integer',
            'depth' => 'integer',
        ];
    }
}
