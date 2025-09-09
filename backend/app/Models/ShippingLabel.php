<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class ShippingLabel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'carrier',
        'service_type',
        'tracking_number',
        'label_url',
        'label_data',
        'cost',
        'status',
        'tracking_events',
        'estimated_delivery_date',
        'actual_delivery_date',
        'notes'
    ];

    protected $casts = [
        'label_data' => 'array',
        'tracking_events' => 'array',
        'cost' => 'decimal:2',
        'estimated_delivery_date' => 'datetime',
        'actual_delivery_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $dates = [
        'estimated_delivery_date',
        'actual_delivery_date'
    ];

    /**
     * Relationship with Order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get formatted cost
     */
    public function getFormattedCostAttribute(): string
    {
        return '$' . number_format($this->cost, 0, ',', '.');
    }

    /**
     * Get carrier name
     */
    public function getCarrierNameAttribute(): string
    {
        $carriers = [
            'coordinadora' => 'Coordinadora',
            'servientrega' => 'Servientrega',
            'interrapidisimo' => 'Interrapidísimo',
            'tcc' => 'TCC',
            'fedex' => 'FedEx',
            'dhl' => 'DHL'
        ];

        return $carriers[$this->carrier] ?? ucfirst($this->carrier);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'created' => 'Creada',
            'printed' => 'Impresa',
            'picked_up' => 'Recogida',
            'in_transit' => 'En tránsito',
            'out_for_delivery' => 'En reparto',
            'delivered' => 'Entregada',
            'exception' => 'Excepción',
            'returned' => 'Devuelta',
            'cancelled' => 'Cancelada'
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        $colors = [
            'created' => 'blue',
            'printed' => 'indigo',
            'picked_up' => 'purple',
            'in_transit' => 'yellow',
            'out_for_delivery' => 'orange',
            'delivered' => 'green',
            'exception' => 'red',
            'returned' => 'gray',
            'cancelled' => 'red'
        ];

        return $colors[$this->status] ?? 'gray';
    }

    /**
     * Get latest tracking event
     */
    public function getLatestTrackingEventAttribute(): ?array
    {
        if (empty($this->tracking_events)) {
            return null;
        }

        return end($this->tracking_events);
    }

    /**
     * Get estimated delivery date formatted
     */
    public function getFormattedEstimatedDeliveryAttribute(): ?string
    {
        return $this->estimated_delivery_date?->format('d/m/Y');
    }

    /**
     * Get actual delivery date formatted
     */
    public function getFormattedActualDeliveryAttribute(): ?string
    {
        return $this->actual_delivery_date?->format('d/m/Y H:i');
    }

    /**
     * Check if label is delivered
     */
    public function getIsDeliveredAttribute(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Check if label is in transit
     */
    public function getIsInTransitAttribute(): bool
    {
        return in_array($this->status, ['picked_up', 'in_transit', 'out_for_delivery']);
    }

    /**
     * Check if label has exception
     */
    public function getHasExceptionAttribute(): bool
    {
        return in_array($this->status, ['exception', 'returned']);
    }

    /**
     * Get delivery time in days
     */
    public function getDeliveryTimeAttribute(): ?int
    {
        if (!$this->actual_delivery_date) {
            return null;
        }

        return $this->created_at->diffInDays($this->actual_delivery_date);
    }

    /**
     * Check if delivery was on time
     */
    public function getIsOnTimeAttribute(): ?bool
    {
        if (!$this->actual_delivery_date || !$this->estimated_delivery_date) {
            return null;
        }

        return $this->actual_delivery_date->lte($this->estimated_delivery_date);
    }

    /**
     * Add tracking event
     */
    public function addTrackingEvent(array $event): void
    {
        $events = $this->tracking_events ?? [];
        $events[] = array_merge($event, [
            'timestamp' => now()->toISOString()
        ]);
        
        $this->update(['tracking_events' => $events]);
    }

    /**
     * Update status and add event
     */
    public function updateStatus(string $status, array $eventData = []): void
    {
        $this->update(['status' => $status]);
        
        $this->addTrackingEvent(array_merge([
            'status' => $status,
            'description' => $this->getStatusLabelAttribute()
        ], $eventData));
        
        // Update delivery date if delivered
        if ($status === 'delivered' && !$this->actual_delivery_date) {
            $this->update(['actual_delivery_date' => now()]);
        }
    }

    /**
     * Scope for specific carrier
     */
    public function scopeByCarrier($query, string $carrier)
    {
        return $query->where('carrier', $carrier);
    }

    /**
     * Scope for specific status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for delivered labels
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Scope for in transit labels
     */
    public function scopeInTransit($query)
    {
        return $query->whereIn('status', ['picked_up', 'in_transit', 'out_for_delivery']);
    }

    /**
     * Scope for labels with exceptions
     */
    public function scopeWithExceptions($query)
    {
        return $query->whereIn('status', ['exception', 'returned']);
    }

    /**
     * Scope for recent labels
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}