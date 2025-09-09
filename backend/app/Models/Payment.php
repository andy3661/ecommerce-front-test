<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'user_id',
        'payment_method',
        'gateway_payment_id',
        'amount',
        'currency',
        'status',
        'reference',
        'gateway_data',
        'completed_at',
        'failed_at',
        'failure_reason'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_data' => 'array',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime'
    ];

    /**
     * Get the order that owns the payment
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user that owns the payment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the formatted amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }

    /**
     * Get the payment method label
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match($this->payment_method) {
            'stripe' => 'Stripe',
            'paypal' => 'PayPal',
            'payu' => 'PayU',
            'wompi' => 'Wompi',
            'mercadopago' => 'MercadoPago',
            default => ucfirst($this->payment_method)
        };
    }

    /**
     * Get the status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            default => ucfirst($this->status)
        };
    }

    /**
     * Get the status color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'processing' => 'info',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'secondary',
            'refunded' => 'primary',
            default => 'secondary'
        };
    }

    /**
     * Check if payment is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    /**
     * Check if payment has failed
     */
    public function hasFailed(): bool
    {
        return in_array($this->status, ['failed', 'cancelled']);
    }

    /**
     * Check if payment can be refunded
     */
    public function canBeRefunded(): bool
    {
        return $this->status === 'completed' && 
               $this->completed_at && 
               $this->completed_at->diffInDays(now()) <= 30; // 30 days refund policy
    }

    /**
     * Mark payment as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason
        ]);
    }

    /**
     * Mark payment as refunded
     */
    public function markAsRefunded(): void
    {
        $this->update([
            'status' => 'refunded'
        ]);
    }

    /**
     * Scope to get payments by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get successful payments
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get pending payments
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }

    /**
     * Scope to get failed payments
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'cancelled']);
    }

    /**
     * Scope to get payments by method
     */
    public function scopeByMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope to get payments in date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get payments by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}