<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'user_id',
        'order_id',
        'discount_amount',
        'used_at'
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'used_at' => 'datetime'
    ];

    /**
     * Get the coupon that was used
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Get the user who used the coupon
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order where the coupon was used
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the formatted discount amount
     */
    public function getFormattedDiscountAmountAttribute(): string
    {
        return '$' . number_format($this->discount_amount, 2);
    }

    /**
     * Scope to get usages by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get usages by coupon
     */
    public function scopeByCoupon($query, $couponId)
    {
        return $query->where('coupon_id', $couponId);
    }

    /**
     * Scope to get usages in date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('used_at', [$startDate, $endDate]);
    }
}