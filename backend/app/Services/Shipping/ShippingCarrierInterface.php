<?php

namespace App\Services\Shipping;

interface ShippingCarrierInterface
{
    /**
     * Get carrier name
     */
    public function getName(): string;

    /**
     * Get available shipping methods for destination
     */
    public function getAvailableMethods(array $destination): array;

    /**
     * Calculate shipping cost
     */
    public function calculateShipping(array $data): array;

    /**
     * Create shipping label
     */
    public function createLabel(array $data): array;

    /**
     * Track shipment
     */
    public function trackShipment(string $trackingNumber): array;

    /**
     * Get carrier coverage information
     */
    public function getCoverage(array $location = []): array;

    /**
     * Process webhook from carrier
     */
    public function processWebhook(array $payload): ?array;

    /**
     * Get estimated delivery time
     */
    public function getEstimatedDeliveryTime(string $serviceType, array $destination): array;

    /**
     * Validate carrier configuration
     */
    public function isConfigured(): bool;

    /**
     * Get supported service types
     */
    public function getSupportedServices(): array;

    /**
     * Get maximum weight limit
     */
    public function getMaxWeight(): float;

    /**
     * Get maximum dimensions
     */
    public function getMaxDimensions(): array;

    /**
     * Check if destination is supported
     */
    public function supportsDestination(array $destination): bool;
}