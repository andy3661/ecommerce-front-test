<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'product_variant',
        'quantity',
        'unit_price',
        'total_price',
        'product_snapshot'
    ];

    protected $casts = [
        'product_variant' => 'array',
        'product_snapshot' => 'array',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2'
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Accessors
    public function getFormattedUnitPriceAttribute(): string
    {
        return '$' . number_format($this->unit_price, 2);
    }

    public function getFormattedTotalPriceAttribute(): string
    {
        return '$' . number_format($this->total_price, 2);
    }

    public function getVariantDisplayAttribute(): ?string
    {
        if (!$this->product_variant) {
            return null;
        }

        $variants = [];
        foreach ($this->product_variant as $key => $value) {
            $variants[] = ucfirst($key) . ': ' . $value;
        }

        return implode(', ', $variants);
    }

    // Helper methods
    public function calculateTotal(): float
    {
        return $this->quantity * $this->unit_price;
    }

    public function updateTotal(): bool
    {
        $this->total_price = $this->calculateTotal();
        return $this->save();
    }
}