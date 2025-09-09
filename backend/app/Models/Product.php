<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'sku',
        'barcode',
        'price',
        'compare_price',
        'cost_price',
        'track_inventory',
        'inventory_quantity',
        'low_stock_threshold',
        'weight',
        'weight_unit',
        'dimensions',
        'status',
        'is_featured',
        'is_digital',
        'requires_shipping',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'images',
        'gallery',
        'attributes',
        'variants',
        'sort_order',
        'published_at'
    ];

    protected $casts = [
        'dimensions' => 'array',
        'meta_keywords' => 'array',
        'images' => 'array',
        'gallery' => 'array',
        'attributes' => 'array',
        'variants' => 'array',
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'track_inventory' => 'boolean',
        'is_featured' => 'boolean',
        'is_digital' => 'boolean',
        'requires_shipping' => 'boolean',
        'published_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    // Relationships
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }

    public function primaryCategory(): BelongsToMany
    {
        return $this->categories()->wherePivot('is_primary', true);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('is_approved', true);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tags')
                    ->withTimestamps();
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published_at', '<=', now())
                    ->whereNotNull('published_at');
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('track_inventory', false)
              ->orWhere('inventory_quantity', '>', 0);
        });
    }

    public function scopeByCategory(Builder $query, $categoryId): Builder
    {
        return $query->whereHas('categories', function ($q) use ($categoryId) {
            $q->where('categories.id', $categoryId);
        });
    }

    public function scopePriceRange(Builder $query, $minPrice = null, $maxPrice = null): Builder
    {
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }
        return $query;
    }

    // Accessors & Mutators
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price, 2);
    }

    public function getIsOnSaleAttribute(): bool
    {
        return $this->compare_price && $this->compare_price > $this->price;
    }

    public function getDiscountPercentageAttribute(): ?float
    {
        if (!$this->is_on_sale) {
            return null;
        }
        return round((($this->compare_price - $this->price) / $this->compare_price) * 100, 2);
    }

    public function getAverageRatingAttribute(): float
    {
        return $this->approvedReviews()->avg('rating') ?? 0;
    }

    public function getReviewsCountAttribute(): int
    {
        return $this->approvedReviews()->count();
    }

    public function getIsInStockAttribute(): bool
    {
        if (!$this->track_inventory) {
            return true;
        }
        return $this->inventory_quantity > 0;
    }

    public function getIsLowStockAttribute(): bool
    {
        if (!$this->track_inventory) {
            return false;
        }
        return $this->inventory_quantity <= $this->low_stock_threshold;
    }

    public function getFeaturedImageAttribute(): ?string
    {
        return $this->images[0] ?? null;
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'price' => $this->price,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'categories' => $this->categories->pluck('name')->toArray(),
            'category_ids' => $this->categories->pluck('id')->toArray(),
            'tags' => $this->tags->pluck('name')->toArray(),
            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,
        ];
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get the index name for the model.
     */
    public function searchableAs(): string
    {
        return 'products_index';
    }
}