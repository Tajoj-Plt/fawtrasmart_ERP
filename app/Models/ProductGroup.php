<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'code',
        'level',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer',
    ];

    /**
     * Get the parent group.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'parent_id');
    }

    /**
     * Get the child groups.
     */
    public function children(): HasMany
    {
        return $this->hasMany(ProductGroup::class, 'parent_id');
    }

    /**
     * Get all descendants recursively.
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all ancestors recursively.
     */
    public function ancestors(): BelongsTo
    {
        return $this->parent()->with('ancestors');
    }

    /**
     * Get the classifications for this group.
     */
    public function classifications(): HasMany
    {
        return $this->hasMany(Category::class, 'product_group_id');
    }

    /**
     * Get the categories for this group (alias for classifications).
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'product_group_id');
    }

    /**
     * Scope to get only active groups.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get root groups (no parent).
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get the full path of the group.
     */
    public function getFullPathAttribute(): string
    {
        $path = collect([$this->name]);
        $parent = $this->parent;

        while ($parent) {
            $path->prepend($parent->name);
            $parent = $parent->parent;
        }

        return $path->implode(' > ');
    }
}
