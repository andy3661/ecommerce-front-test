<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'icon',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'parent_id',
        'sort_order',
        'is_active',
        'is_featured',
        'status'
    ];

    protected $casts = [
        'meta_keywords' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    // Relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
                    ->where('is_active', true)
                    ->orderBy('sort_order');
    }

    public function allChildren(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
                    ->orderBy('sort_order');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_categories')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }

    public function activeProducts(): BelongsToMany
    {
        return $this->products()
                    ->where('products.status', 'active')
                    ->where('products.published_at', '<=', now())
                    ->whereNotNull('products.published_at');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeRootCategories(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeWithChildren(Builder $query): Builder
    {
        return $query->with(['children' => function ($q) {
            $q->active()->orderBy('sort_order');
        }]);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Helper Methods
    public function getAncestors(): \Illuminate\Support\Collection
    {
        $ancestors = collect();
        $category = $this->parent;
        
        while ($category) {
            $ancestors->prepend($category);
            $category = $category->parent;
        }
        
        return $ancestors;
    }

    public function getDescendants(): \Illuminate\Support\Collection
    {
        $descendants = collect();
        
        foreach ($this->allChildren as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }
        
        return $descendants;
    }

    public function getBreadcrumb(): array
    {
        $breadcrumb = [];
        $ancestors = $this->getAncestors();
        
        foreach ($ancestors as $ancestor) {
            $breadcrumb[] = [
                'id' => $ancestor->id,
                'name' => $ancestor->name,
                'slug' => $ancestor->slug
            ];
        }
        
        $breadcrumb[] = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug
        ];
        
        return $breadcrumb;
    }

    public function getLevel(): int
    {
        return $this->getAncestors()->count();
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function isChildOf(Category $category): bool
    {
        return $this->getAncestors()->contains('id', $category->id);
    }

    public function isParentOf(Category $category): bool
    {
        return $category->isChildOf($this);
    }

    // Accessors
    public function getProductsCountAttribute(): int
    {
        return $this->activeProducts()->count();
    }

    public function getFullNameAttribute(): string
    {
        $ancestors = $this->getAncestors();
        $names = $ancestors->pluck('name')->toArray();
        $names[] = $this->name;
        
        return implode(' > ', $names);
    }
}