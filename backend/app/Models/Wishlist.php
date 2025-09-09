<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Wishlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Scopes
    public function scopeForUser(Builder $query, $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithActiveProducts(Builder $query): Builder
    {
        return $query->whereHas('product', function ($q) {
            $q->where('status', 'active')
              ->where('published_at', '<=', now())
              ->whereNotNull('published_at');
        });
    }

    // Helper methods
    public static function toggle($userId, $productId): array
    {
        $wishlistItem = static::where('user_id', $userId)
                             ->where('product_id', $productId)
                             ->first();

        if ($wishlistItem) {
            $wishlistItem->delete();
            return [
                'action' => 'removed',
                'message' => 'Product removed from wishlist'
            ];
        } else {
            static::create([
                'user_id' => $userId,
                'product_id' => $productId
            ]);
            return [
                'action' => 'added',
                'message' => 'Product added to wishlist'
            ];
        }
    }

    public static function isInWishlist($userId, $productId): bool
    {
        return static::where('user_id', $userId)
                    ->where('product_id', $productId)
                    ->exists();
    }
}