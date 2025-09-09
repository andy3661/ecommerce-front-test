<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ShippingLabel;
use App\Services\Shipping\ShippingCarrierInterface;
use App\Services\Shipping\ShippingCarrierFactory;
use Illuminate\Support\Facades\Log;
use Exception;

class ShippingService
{
    protected ShippingCarrierFactory $carrierFactory;

    public function __construct(ShippingCarrierFactory $carrierFactory)
    {
        $this->carrierFactory = $carrierFactory;
    }

    /**
     * Get available shipping methods for a destination
     */
    public function getAvailableMethods(array $data): array
    {
        $methods = [];
        $enabledCarriers = $this->carrierFactory->getEnabledCarriers();

        foreach ($enabledCarriers as $carrierName) {
            try {
                $carrier = $this->carrierFactory->create($carrierName);
                $carrierMethods = $carrier->getAvailableMethods($data);
                
                foreach ($carrierMethods as $method) {
                    $methods[] = array_merge($method, [
                        'carrier' => $carrierName,
                        'carrier_name' => $carrier->getName()
                    ]);
                }
            } catch (Exception $e) {
                Log::warning("Failed to get methods from carrier {$carrierName}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Sort by price
        usort($methods, function ($a, $b) {
            return $a['price'] <=> $b['price'];
        });

        return $methods;
    }

    /**
     * Calculate shipping cost for specific carrier and service
     */
    public function calculateShipping(array $data): array
    {
        $carrier = $this->carrierFactory->create($data['carrier']);
        
        return $carrier->calculateShipping($data);
    }

    /**
     * Create shipping label
     */
    public function createShippingLabel(array $data): array
    {
        $carrier = $this->carrierFactory->create($data['carrier']);
        $order = Order::findOrFail($data['order_id']);
        
        // Create label with carrier
        $labelData = $carrier->createLabel($data);
        
        // Save label to database
        $shippingLabel = ShippingLabel::create([
            'order_id' => $order->id,
            'carrier' => $data['carrier'],
            'service_type' => $data['service_type'],
            'tracking_number' => $labelData['tracking_number'],
            'label_url' => $labelData['label_url'] ?? null,
            'label_data' => $labelData,
            'cost' => $labelData['cost'] ?? 0,
            'status' => 'created'
        ]);
        
        // Update order with tracking information
        $order->update([
            'tracking_number' => $labelData['tracking_number'],
            'shipping_carrier' => $data['carrier'],
            'shipping_service' => $data['service_type'],
            'shipping_cost' => $labelData['cost'] ?? 0,
            'status' => 'shipped'
        ]);
        
        return array_merge($labelData, [
            'label_id' => $shippingLabel->id
        ]);
    }

    /**
     * Track shipment
     */
    public function trackShipment(string $trackingNumber, string $carrierName): array
    {
        $carrier = $this->carrierFactory->create($carrierName);
        
        return $carrier->trackShipment($trackingNumber);
    }

    /**
     * Get shipping zones configuration
     */
    public function getShippingZones(): array
    {
        return [
            'national' => [
                'name' => 'Nacional',
                'countries' => ['CO'],
                'base_cost' => 8000,
                'per_kg_cost' => 2000,
                'free_shipping_threshold' => 150000
            ],
            'international' => [
                'name' => 'Internacional',
                'countries' => ['US', 'CA', 'MX', 'PA', 'EC', 'PE', 'VE', 'BR', 'AR', 'CL'],
                'base_cost' => 25000,
                'per_kg_cost' => 8000,
                'free_shipping_threshold' => 500000
            ],
            'express' => [
                'name' => 'Express Nacional',
                'countries' => ['CO'],
                'cities' => ['Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Cartagena'],
                'base_cost' => 15000,
                'per_kg_cost' => 3000,
                'delivery_time' => '24-48 horas'
            ]
        ];
    }

    /**
     * Get carrier coverage information
     */
    public function getCarrierCoverage(string $carrierName, array $location = []): array
    {
        $carrier = $this->carrierFactory->create($carrierName);
        
        return $carrier->getCoverage($location);
    }

    /**
     * Process webhook from shipping carrier
     */
    public function processWebhook(string $carrierName, array $payload): bool
    {
        try {
            $carrier = $this->carrierFactory->create($carrierName);
            $trackingData = $carrier->processWebhook($payload);
            
            if (!$trackingData) {
                return false;
            }
            
            // Find order by tracking number
            $order = Order::where('tracking_number', $trackingData['tracking_number'])->first();
            
            if (!$order) {
                Log::warning('Order not found for tracking number', [
                    'tracking_number' => $trackingData['tracking_number'],
                    'carrier' => $carrierName
                ]);
                return false;
            }
            
            // Update shipping label
            $shippingLabel = ShippingLabel::where('order_id', $order->id)
                ->where('tracking_number', $trackingData['tracking_number'])
                ->first();
                
            if ($shippingLabel) {
                $shippingLabel->update([
                    'status' => $trackingData['status'],
                    'tracking_events' => array_merge(
                        $shippingLabel->tracking_events ?? [],
                        [$trackingData['event']]
                    ),
                    'updated_at' => now()
                ]);
            }
            
            // Update order status based on shipping status
            $this->updateOrderStatusFromShipping($order, $trackingData['status']);
            
            return true;
            
        } catch (Exception $e) {
            Log::error('Failed to process shipping webhook', [
                'error' => $e->getMessage(),
                'carrier' => $carrierName,
                'payload' => $payload
            ]);
            
            return false;
        }
    }

    /**
     * Update order status based on shipping status
     */
    protected function updateOrderStatusFromShipping(Order $order, string $shippingStatus): void
    {
        $statusMapping = [
            'in_transit' => 'shipped',
            'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'exception' => 'shipping_exception',
            'returned' => 'returned'
        ];
        
        if (isset($statusMapping[$shippingStatus])) {
            $order->update([
                'status' => $statusMapping[$shippingStatus],
                'delivered_at' => $shippingStatus === 'delivered' ? now() : null
            ]);
        }
    }

    /**
     * Calculate shipping cost based on weight and destination
     */
    public function calculateBasicShipping(float $weight, string $destinationCity, string $destinationState): float
    {
        $zones = $this->getShippingZones();
        
        // Determine zone based on destination
        $zone = 'national'; // Default
        
        if (in_array($destinationCity, $zones['express']['cities'])) {
            $zone = 'express';
        }
        
        $zoneConfig = $zones[$zone];
        
        return $zoneConfig['base_cost'] + ($weight * $zoneConfig['per_kg_cost']);
    }

    /**
     * Check if order qualifies for free shipping
     */
    public function qualifiesForFreeShipping(float $orderTotal, string $destinationCountry = 'CO'): bool
    {
        $zones = $this->getShippingZones();
        
        $threshold = $destinationCountry === 'CO' 
            ? $zones['national']['free_shipping_threshold']
            : $zones['international']['free_shipping_threshold'];
            
        return $orderTotal >= $threshold;
    }

    /**
     * Get estimated delivery time
     */
    public function getEstimatedDeliveryTime(string $carrierName, string $serviceType, array $destination): array
    {
        try {
            $carrier = $this->carrierFactory->create($carrierName);
            return $carrier->getEstimatedDeliveryTime($serviceType, $destination);
        } catch (Exception $e) {
            Log::warning('Failed to get delivery time estimate', [
                'error' => $e->getMessage(),
                'carrier' => $carrierName,
                'service' => $serviceType
            ]);
            
            // Return default estimates
            return [
                'min_days' => 3,
                'max_days' => 7,
                'description' => '3-7 días hábiles'
            ];
        }
    }

    /**
     * Validate shipping address
     */
    public function validateAddress(array $address): array
    {
        $errors = [];
        
        $required = ['address', 'city', 'state', 'postal_code', 'country'];
        
        foreach ($required as $field) {
            if (empty($address[$field])) {
                $errors[] = "Field {$field} is required";
            }
        }
        
        // Additional validations can be added here
        // e.g., postal code format validation, city/state validation
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}