<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'payment_status',
        'fulfillment_status',
        'customer_email',
        'customer_phone',
        'billing_address',
        'shipping_address',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'shipping_method',
        'tracking_number',
        'shipped_at',
        'delivered_at',
        'payment_method',
        'payment_gateway',
        'payment_reference',
        'paid_at',
        'notes',
        'metadata',
        'coupon_code',
        'discount_percentage',
        'shipping_carrier',
        'shipping_service_type',
        'shipping_service_name',
        'shipping_cost',
        'shipping_currency',
        'shipping_status',
        'estimated_delivery_date',
        'actual_delivery_date',
        'package_weight',
        'package_dimensions',
        'declared_value',
        'requires_signature',
        'shipping_insurance',
        'insurance_cost',
        'shipping_zone',
        'free_shipping',
        'free_shipping_threshold',
        'shipping_notes',
        'delivery_instructions',
        'cancelled_at',
        'internal_notes',
        'customer_notes'
    ];

    protected $casts = [
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'metadata' => 'array',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'paid_at' => 'datetime',
        'discount_percentage' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'package_weight' => 'decimal:3',
        'package_dimensions' => 'array',
        'declared_value' => 'decimal:2',
        'requires_signature' => 'boolean',
        'shipping_insurance' => 'boolean',
        'insurance_cost' => 'decimal:2',
        'free_shipping' => 'boolean',
        'free_shipping_threshold' => 'decimal:2',
        'estimated_delivery_date' => 'date',
        'actual_delivery_date' => 'datetime',
        'cancelled_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Get the payments for the order.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the shipping labels for the order.
     */
    public function shippingLabels()
    {
        return $this->hasMany(ShippingLabel::class);
    }

    /**
     * Get the primary shipping label for the order.
     */
    public function primaryShippingLabel()
    {
        return $this->hasOne(ShippingLabel::class)->where('is_return', false)->latest();
    }

    // Scopes
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentStatus(Builder $query, string $paymentStatus): Builder
    {
        return $query->where('payment_status', $paymentStatus);
    }

    /**
     * Scope to filter by shipping status
     */
    public function scopeByShippingStatus($query, $status)
    {
        return $query->where('shipping_status', $status);
    }

    /**
     * Scope to filter by shipping carrier
     */
    public function scopeByShippingCarrier($query, $carrier)
    {
        return $query->where('shipping_carrier', $carrier);
    }

    /**
     * Scope to filter orders that need shipping
     */
    public function scopeNeedsShipping($query)
    {
        return $query->whereIn('shipping_status', ['pending', 'processing']);
    }

    public function scopeByFulfillmentStatus(Builder $query, string $fulfillmentStatus): Builder
    {
        return $query->where('fulfillment_status', $fulfillmentStatus);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    public function scopeShipped(Builder $query): Builder
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Scope to filter shipped orders
     */
    public function scopeShippedByStatus($query)
    {
        return $query->whereIn('shipping_status', ['shipped', 'in_transit', 'out_for_delivery']);
    }

    /**
     * Scope to filter delivered orders
     */
    public function scopeDeliveredByStatus($query)
    {
        return $query->where('shipping_status', 'delivered');
    }

    // Accessors
    public function getFormattedSubtotalAttribute(): string
    {
        return '$' . number_format($this->subtotal, 2);
    }

    public function getFormattedTaxAmountAttribute(): string
    {
        return '$' . number_format($this->tax_amount, 2);
    }

    public function getFormattedShippingAmountAttribute(): string
    {
        return '$' . number_format($this->shipping_amount, 2);
    }

    public function getFormattedDiscountAmountAttribute(): string
    {
        return '$' . number_format($this->discount_amount, 2);
    }

    public function getFormattedTotalAmountAttribute(): string
    {
        return '$' . number_format($this->total_amount, 2);
    }

    public function getItemsCountAttribute(): int
    {
        return $this->items()->sum('quantity');
    }

    public function getCanBeCancelledAttribute(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function getCanBeRefundedAttribute(): bool
    {
        return $this->payment_status === 'paid' && 
               in_array($this->status, ['delivered', 'shipped']);
    }

    public function getIsShippedAttribute(): bool
    {
        return in_array($this->status, ['shipped', 'delivered']);
    }

    public function getIsDeliveredAttribute(): bool
    {
        return $this->status === 'delivered';
    }

    // Helper methods
    public static function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-' . strtoupper(Str::random(8));
        } while (static::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    public function calculateTotals(): void
    {
        $this->subtotal = $this->items()->sum('total_price');
        $this->total_amount = $this->subtotal + $this->tax_amount + $this->shipping_amount - $this->discount_amount;
    }

    public function markAsPaid(string $paymentReference = null): bool
    {
        $this->payment_status = 'paid';
        $this->paid_at = now();
        if ($paymentReference) {
            $this->payment_reference = $paymentReference;
        }
        return $this->save();
    }

    public function markAsShipped(string $trackingNumber = null): bool
    {
        $this->status = 'shipped';
        $this->fulfillment_status = 'fulfilled';
        $this->shipped_at = now();
        if ($trackingNumber) {
            $this->tracking_number = $trackingNumber;
        }
        return $this->save();
    }

    public function markAsDelivered(): bool
    {
        $this->status = 'delivered';
        $this->delivered_at = now();
        return $this->save();
    }

    public function cancel(string $reason = null): bool
    {
        if (!$this->can_be_cancelled) {
            return false;
        }

        $this->status = 'cancelled';
        if ($reason) {
            $metadata = $this->metadata ?? [];
            $metadata['cancellation_reason'] = $reason;
            $this->metadata = $metadata;
        }
        return $this->save();
    }

    /**
     * Check if order is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if order has been shipped
     */
    public function isShippedByStatus(): bool
    {
        return in_array($this->shipping_status, ['shipped', 'in_transit', 'out_for_delivery', 'delivered']);
    }

    /**
     * Check if order has been delivered
     */
    public function isDeliveredByStatus(): bool
    {
        return $this->shipping_status === 'delivered';
    }

    /**
     * Check if order needs shipping
     */
    public function needsShipping(): bool
    {
        return in_array($this->shipping_status, ['pending', 'processing']);
    }

    /**
     * Get formatted shipping cost
     */
    public function getFormattedShippingCostAttribute(): string
    {
        return number_format($this->shipping_cost, 0, ',', '.') . ' ' . $this->shipping_currency;
    }

    /**
     * Get shipping status label
     */
    public function getShippingStatusLabelAttribute(): string
    {
        return match ($this->shipping_status) {
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'shipped' => 'Enviado',
            'in_transit' => 'En tránsito',
            'out_for_delivery' => 'En reparto',
            'delivered' => 'Entregado',
            'returned' => 'Devuelto',
            'cancelled' => 'Cancelado',
            'exception' => 'Excepción',
            default => 'Desconocido'
        };
    }

    /**
     * Get shipping status color
     */
    public function getShippingStatusColorAttribute(): string
    {
        return match ($this->shipping_status) {
            'pending' => 'warning',
            'processing' => 'info',
            'shipped' => 'primary',
            'in_transit' => 'primary',
            'out_for_delivery' => 'success',
            'delivered' => 'success',
            'returned' => 'secondary',
            'cancelled' => 'danger',
            'exception' => 'danger',
            default => 'secondary'
        };
    }

    /**
     * Get total weight including all items
     */
    public function getTotalWeightAttribute(): float
    {
        if ($this->package_weight) {
            return $this->package_weight;
        }

        return $this->items->sum(function ($item) {
            return ($item->product->weight ?? 0.5) * $item->quantity;
        });
    }

    /**
     * Check if order qualifies for free shipping
     */
    public function qualifiesForFreeShipping(): bool
    {
        if ($this->free_shipping) {
            return true;
        }

        if ($this->free_shipping_threshold && $this->subtotal >= $this->free_shipping_threshold) {
            return true;
        }

        return false;
    }

    /**
     * Get the order status history
     */
    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }
}