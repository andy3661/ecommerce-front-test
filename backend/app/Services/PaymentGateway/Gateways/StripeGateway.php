<?php

namespace App\Services\PaymentGateway\Gateways;

use App\Services\PaymentGateway\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class StripeGateway implements PaymentGatewayInterface
{
    private string $apiKey;
    private string $webhookSecret;
    private string $baseUrl = 'https://api.stripe.com/v1';

    public function __construct()
    {
        $this->apiKey = config('payments.stripe.secret_key');
        $this->webhookSecret = config('payments.stripe.webhook_secret');
    }

    /**
     * Create a payment intent
     */
    public function createPaymentIntent(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ])->asForm()->post($this->baseUrl . '/payment_intents', [
                'amount' => (int) ($data['amount'] * 100), // Convert to cents
                'currency' => strtolower($data['currency']),
                'automatic_payment_methods' => [
                    'enabled' => true
                ],
                'metadata' => array_merge($data['metadata'] ?? [], [
                    'order_id' => $data['order_id'],
                    'payment_id' => $data['payment_id']
                ])
            ]);

            if (!$response->successful()) {
                throw new Exception('Stripe API error: ' . $response->body());
            }

            $paymentIntent = $response->json();

            return [
                'payment_id' => $paymentIntent['id'],
                'client_secret' => $paymentIntent['client_secret'],
                'status' => $this->mapStripeStatus($paymentIntent['status']),
                'gateway_data' => $paymentIntent
            ];

        } catch (Exception $e) {
            Log::error('Stripe payment intent creation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Confirm a payment
     */
    public function confirmPayment(string $paymentId, array $data = []): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ])->asForm()->post($this->baseUrl . '/payment_intents/' . $paymentId . '/confirm', $data);

            if (!$response->successful()) {
                throw new Exception('Stripe API error: ' . $response->body());
            }

            $paymentIntent = $response->json();

            return [
                'status' => $this->mapStripeStatus($paymentIntent['status']),
                'gateway_data' => $paymentIntent
            ];

        } catch (Exception $e) {
            Log::error('Stripe payment confirmation failed', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $paymentId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey
            ])->get($this->baseUrl . '/payment_intents/' . $paymentId);

            if (!$response->successful()) {
                throw new Exception('Stripe API error: ' . $response->body());
            }

            $paymentIntent = $response->json();

            return [
                'status' => $this->mapStripeStatus($paymentIntent['status']),
                'gateway_data' => $paymentIntent
            ];

        } catch (Exception $e) {
            Log::error('Stripe payment status retrieval failed', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId
            ]);
            throw $e;
        }
    }

    /**
     * Refund a payment
     */
    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array
    {
        try {
            $refundData = [
                'payment_intent' => $paymentId
            ];

            if ($amount) {
                $refundData['amount'] = (int) ($amount * 100); // Convert to cents
            }

            if ($reason) {
                $refundData['reason'] = $reason;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ])->asForm()->post($this->baseUrl . '/refunds', $refundData);

            if (!$response->successful()) {
                throw new Exception('Stripe API error: ' . $response->body());
            }

            $refund = $response->json();

            return [
                'refund_id' => $refund['id'],
                'status' => $refund['status'],
                'amount' => $refund['amount'] / 100, // Convert from cents
                'gateway_data' => $refund
            ];

        } catch (Exception $e) {
            Log::error('Stripe refund failed', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
                'amount' => $amount,
                'reason' => $reason
            ]);
            throw $e;
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('Stripe-Signature');

            if (!$signature || !$this->webhookSecret) {
                return false;
            }

            $elements = explode(',', $signature);
            $signatureData = [];

            foreach ($elements as $element) {
                $parts = explode('=', $element, 2);
                if (count($parts) === 2) {
                    $signatureData[$parts[0]] = $parts[1];
                }
            }

            if (!isset($signatureData['t']) || !isset($signatureData['v1'])) {
                return false;
            }

            $timestamp = $signatureData['t'];
            $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $payload, $this->webhookSecret);

            return hash_equals($expectedSignature, $signatureData['v1']);

        } catch (Exception $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Process webhook data
     */
    public function processWebhook(array $payload): ?array
    {
        try {
            $eventType = $payload['type'] ?? null;
            $eventData = $payload['data']['object'] ?? null;

            if (!$eventType || !$eventData) {
                return null;
            }

            // Only process payment intent events
            if (!str_starts_with($eventType, 'payment_intent.')) {
                return null;
            }

            $paymentId = $eventData['id'];
            $status = $this->mapStripeStatus($eventData['status']);

            return [
                'payment_id' => $paymentId,
                'status' => $status,
                'event_type' => $eventType,
                'gateway_data' => $eventData
            ];

        } catch (Exception $e) {
            Log::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            return null;
        }
    }

    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies(): array
    {
        return ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'SEK', 'NOK', 'DKK'];
    }

    /**
     * Get gateway configuration
     */
    public function getConfig(): array
    {
        return [
            'name' => 'Stripe',
            'enabled' => config('payments.stripe.enabled', false),
            'public_key' => config('payments.stripe.public_key'),
            'currencies' => $this->getSupportedCurrencies()
        ];
    }

    /**
     * Validate gateway configuration
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty(config('payments.stripe.public_key'));
    }

    /**
     * Map Stripe status to internal status
     */
    private function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'requires_payment_method', 'requires_confirmation', 'requires_action' => 'pending',
            'processing' => 'processing',
            'succeeded' => 'completed',
            'requires_capture' => 'pending',
            'canceled' => 'cancelled',
            default => 'failed'
        };
    }
}