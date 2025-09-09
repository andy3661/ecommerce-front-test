<?php

namespace App\Services\Shipping;

use App\Services\Shipping\Carriers\CoordinadoraCarrier;
use App\Services\Shipping\Carriers\ServientregaCarrier;
use App\Services\Shipping\Carriers\InterrapidisimoCarrier;
use App\Services\Shipping\Carriers\TccCarrier;
use App\Services\Shipping\Carriers\FedexCarrier;
use App\Services\Shipping\Carriers\DhlCarrier;
use InvalidArgumentException;

class ShippingCarrierFactory
{
    /**
     * Available carriers
     */
    protected array $carriers = [
        'coordinadora' => CoordinadoraCarrier::class,
        'servientrega' => ServientregaCarrier::class,
        'interrapidisimo' => InterrapidisimoCarrier::class,
        'tcc' => TccCarrier::class,
        'fedex' => FedexCarrier::class,
        'dhl' => DhlCarrier::class,
    ];

    /**
     * Create carrier instance
     */
    public function create(string $carrierName): ShippingCarrierInterface
    {
        if (!isset($this->carriers[$carrierName])) {
            throw new InvalidArgumentException("Unsupported carrier: {$carrierName}");
        }

        $carrierClass = $this->carriers[$carrierName];
        
        if (!class_exists($carrierClass)) {
            throw new InvalidArgumentException("Carrier class not found: {$carrierClass}");
        }

        $carrier = new $carrierClass();
        
        if (!$carrier instanceof ShippingCarrierInterface) {
            throw new InvalidArgumentException("Carrier must implement ShippingCarrierInterface");
        }

        return $carrier;
    }

    /**
     * Get all available carriers
     */
    public function getAvailableCarriers(): array
    {
        return array_keys($this->carriers);
    }

    /**
     * Get enabled carriers
     */
    public function getEnabledCarriers(): array
    {
        $enabled = [];
        
        foreach ($this->carriers as $name => $class) {
            if (config("shipping.carriers.{$name}.enabled", false)) {
                try {
                    $carrier = $this->create($name);
                    if ($carrier->isConfigured()) {
                        $enabled[] = $name;
                    }
                } catch (\Exception $e) {
                    // Skip carriers that can't be instantiated
                    continue;
                }
            }
        }
        
        return $enabled;
    }

    /**
     * Check if carrier is supported
     */
    public function isSupported(string $carrierName): bool
    {
        return isset($this->carriers[$carrierName]);
    }

    /**
     * Check if carrier is enabled
     */
    public function isEnabled(string $carrierName): bool
    {
        if (!$this->isSupported($carrierName)) {
            return false;
        }
        
        return config("shipping.carriers.{$carrierName}.enabled", false);
    }

    /**
     * Get carrier configuration
     */
    public function getCarrierConfig(string $carrierName): array
    {
        if (!$this->isSupported($carrierName)) {
            throw new InvalidArgumentException("Unsupported carrier: {$carrierName}");
        }
        
        try {
            $carrier = $this->create($carrierName);
            return [
                'name' => $carrier->getName(),
                'enabled' => $this->isEnabled($carrierName),
                'configured' => $carrier->isConfigured(),
                'services' => $carrier->getSupportedServices(),
                'max_weight' => $carrier->getMaxWeight(),
                'max_dimensions' => $carrier->getMaxDimensions()
            ];
        } catch (\Exception $e) {
            return [
                'name' => $carrierName,
                'enabled' => false,
                'configured' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all carriers configuration
     */
    public function getAllCarriersConfig(): array
    {
        $configs = [];
        
        foreach ($this->carriers as $name => $class) {
            $configs[$name] = $this->getCarrierConfig($name);
        }
        
        return $configs;
    }

    /**
     * Register new carrier
     */
    public function registerCarrier(string $name, string $class): void
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Carrier class not found: {$class}");
        }
        
        if (!is_subclass_of($class, ShippingCarrierInterface::class)) {
            throw new InvalidArgumentException("Carrier must implement ShippingCarrierInterface");
        }
        
        $this->carriers[$name] = $class;
    }

    /**
     * Get carriers that support specific destination
     */
    public function getCarriersForDestination(array $destination): array
    {
        $supportedCarriers = [];
        
        foreach ($this->getEnabledCarriers() as $carrierName) {
            try {
                $carrier = $this->create($carrierName);
                if ($carrier->supportsDestination($destination)) {
                    $supportedCarriers[] = $carrierName;
                }
            } catch (\Exception $e) {
                // Skip carriers that fail
                continue;
            }
        }
        
        return $supportedCarriers;
    }

    /**
     * Get best carrier for shipment
     */
    public function getBestCarrier(array $shipmentData, string $criteria = 'price'): ?string
    {
        $availableCarriers = $this->getCarriersForDestination($shipmentData);
        
        if (empty($availableCarriers)) {
            return null;
        }
        
        $bestCarrier = null;
        $bestValue = null;
        
        foreach ($availableCarriers as $carrierName) {
            try {
                $carrier = $this->create($carrierName);
                $quote = $carrier->calculateShipping($shipmentData);
                
                $value = match ($criteria) {
                    'price' => $quote['cost'],
                    'time' => $quote['estimated_days'] ?? 999,
                    'reliability' => $this->getCarrierReliabilityScore($carrierName),
                    default => $quote['cost']
                };
                
                if ($bestValue === null || 
                    ($criteria === 'price' && $value < $bestValue) ||
                    ($criteria === 'time' && $value < $bestValue) ||
                    ($criteria === 'reliability' && $value > $bestValue)) {
                    $bestValue = $value;
                    $bestCarrier = $carrierName;
                }
                
            } catch (\Exception $e) {
                // Skip carriers that fail
                continue;
            }
        }
        
        return $bestCarrier;
    }

    /**
     * Get carrier reliability score (0-100)
     */
    protected function getCarrierReliabilityScore(string $carrierName): int
    {
        // This could be based on historical data, user ratings, etc.
        $scores = [
            'coordinadora' => 85,
            'servientrega' => 80,
            'interrapidisimo' => 75,
            'tcc' => 70,
            'fedex' => 95,
            'dhl' => 90,
        ];
        
        return $scores[$carrierName] ?? 50;
    }
}