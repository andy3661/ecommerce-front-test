<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'product_id',
        'product_variant',
        'quantity',
        'unit_price'
    ];

    protected $casts = [
        'product_variant' => 'array',
        'unit_price' => 'decimal:2'
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

    public function scopeForSession(Builder $query, $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeForGuest(Builder $query, $sessionId): Builder
    {
        return $query->whereNull('user_id')->where('session_id', $sessionId);
    }

    // Accessors
    public function getTotalPriceAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    public function getFormattedUnitPriceAttribute(): string
    {
        return '$' . number_format($this->unit_price, 2);
    }

    public function getFormattedTotalPriceAttribute(): string
    {
        return '$' . number_format($this->total_price, 2);
    }

    // Helper methods
    public function updateQuantity(int $quantity): bool
    {
        if ($quantity <= 0) {
            return $this->delete();
        }

        $this->quantity = $quantity;
        return $this->save();
    }

    public function incrementQuantity(int $amount = 1): bool
    {
        $this->quantity += $amount;
        return $this->save();
    }

    public function decrementQuantity(int $amount = 1): bool
    {
        $newQuantity = $this->quantity - $amount;
        return $this->updateQuantity($newQuantity);
    }
}