<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'used_count',
        'usage_limit_per_user',
        'is_active',
        'starts_at',
        'expires_at'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime'
    ];

    /**
     * Get the orders that used this coupon
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the coupon usages
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Check if coupon is valid
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Check if coupon is valid for user
     */
    public function isValidForUser($userId): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        if ($this->usage_limit_per_user) {
            $userUsageCount = $this->usages()->where('user_id', $userId)->count();
            if ($userUsageCount >= $this->usage_limit_per_user) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate discount amount
     */
    public function calculateDiscount($orderAmount): float
    {
        if (!$this->isValid()) {
            return 0;
        }

        if ($this->min_order_amount && $orderAmount < $this->min_order_amount) {
            return 0;
        }

        $discount = 0;

        if ($this->type === 'percentage') {
            $discount = $orderAmount * ($this->value / 100);
            if ($this->max_discount_amount) {
                $discount = min($discount, $this->max_discount_amount);
            }
        } else {
            $discount = min($this->value, $orderAmount);
        }

        return round($discount, 2);
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }

    /**
     * Get the discount type label
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'percentage' => 'Percentage',
            'fixed' => 'Fixed Amount',
            default => ucfirst($this->type)
        };
    }

    /**
     * Get the formatted value
     */
    public function getFormattedValueAttribute(): string
    {
        if ($this->type === 'percentage') {
            return $this->value . '%';
        }
        
        return '$' . number_format($this->value, 2);
    }

    /**
     * Get the status
     */
    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'scheduled';
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'expired';
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return 'exhausted';
        }

        return 'active';
    }

    /**
     * Scope to get active coupons
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', now());
            });
    }

    /**
     * Scope to get expired coupons
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope to get coupons by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}