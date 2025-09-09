<?php

namespace App\Services\PaymentGateway;

use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Create a payment intent
     *
     * @param array $data
     * @return array
     */
    public function createPaymentIntent(array $data): array;

    /**
     * Confirm a payment
     *
     * @param string $paymentId
     * @param array $data
     * @return array
     */
    public function confirmPayment(string $paymentId, array $data = []): array;

    /**
     * Get payment status
     *
     * @param string $paymentId
     * @return array
     */
    public function getPaymentStatus(string $paymentId): array;

    /**
     * Refund a payment
     *
     * @param string $paymentId
     * @param float|null $amount
     * @param string|null $reason
     * @return array
     */
    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array;

    /**
     * Verify webhook signature
     *
     * @param Request $request
     * @return bool
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Process webhook data
     *
     * @param array $payload
     * @return array|null
     */
    public function processWebhook(array $payload): ?array;

    /**
     * Get supported currencies
     *
     * @return array
     */
    public function getSupportedCurrencies(): array;

    /**
     * Get gateway configuration
     *
     * @return array
     */
    public function getConfig(): array;

    /**
     * Validate gateway configuration
     *
     * @return bool
     */
    public function isConfigured(): bool;
}