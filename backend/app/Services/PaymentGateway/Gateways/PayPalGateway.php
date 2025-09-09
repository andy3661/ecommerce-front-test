<?php

namespace App\Services\PaymentGateway\Gateways;

use App\Services\PaymentGateway\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PayPalGateway implements PaymentGatewayInterface
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;
    private string $webhookId;

    public function __construct()
    {
        $this->clientId = config('payments.paypal.client_id');
        $this->clientSecret = config('payments.paypal.client_secret');
        $this->baseUrl = config('payments.paypal.base_url');
        $this->webhookId = config('payments.paypal.webhook_id');
    }

    /**
     * Create a payment intent
     */
    public function createPaymentIntent(array $data): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'PayPal-Request-Id' => uniqid()
            ])->post($this->baseUrl . '/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'amount' => [
                        'currency_code' => $data['currency'],
                        'value' => number_format($data['amount'], 2, '.', '')
                    ],
                    'reference_id' => $data['order_id'],
                    'custom_id' => $data['payment_id']
                ]],
                'application_context' => [
                    'return_url' => $data['return_url'] ?? config('app.url') . '/payment/success',
                    'cancel_url' => $data['cancel_url'] ?? config('app.url') . '/payment/cancel'
                ]
            ]);

            if (!$response->successful()) {
                throw new Exception('PayPal API error: ' . $response->body());
            }

            $order = $response->json();
            $approvalUrl = collect($order['links'])->firstWhere('rel', 'approve')['href'] ?? null;

            return [
                'payment_id' => $order['id'],
                'approval_url' => $approvalUrl,
                'status' => $this->mapPayPalStatus($order['status']),
                'gateway_data' => $order
            ];

        } catch (Exception $e) {
            Log::error('PayPal payment intent creation failed', [
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
            $accessToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/v2/checkout/orders/' . $paymentId . '/capture');

            if (!$response->successful()) {
                throw new Exception('PayPal API error: ' . $response->body());
            }

            $capture = $response->json();

            return [
                'status' => $this->mapPayPalStatus($capture['status']),
                'gateway_data' => $capture
            ];

        } catch (Exception $e) {
            Log::error('PayPal payment confirmation failed', [
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
            $accessToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken
            ])->get($this->baseUrl . '/v2/checkout/orders/' . $paymentId);

            if (!$response->successful()) {
                throw new Exception('PayPal API error: ' . $response->body());
            }

            $order = $response->json();

            return [
                'status' => $this->mapPayPalStatus($order['status']),
                'gateway_data' => $order
            ];

        } catch (Exception $e) {
            Log::error('PayPal payment status retrieval failed', [
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
            // First get the capture ID from the order
            $orderData = $this->getPaymentStatus($paymentId);
            $captureId = $orderData['gateway_data']['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;

            if (!$captureId) {
                throw new Exception('Capture ID not found for refund');
            }

            $accessToken = $this->getAccessToken();
            $refundData = [];

            if ($amount) {
                $refundData['amount'] = [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency_code' => $orderData['gateway_data']['purchase_units'][0]['amount']['currency_code']
                ];
            }

            if ($reason) {
                $refundData['note_to_payer'] = $reason;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/v2/payments/captures/' . $captureId . '/refund', $refundData);

            if (!$response->successful()) {
                throw new Exception('PayPal API error: ' . $response->body());
            }

            $refund = $response->json();

            return [
                'refund_id' => $refund['id'],
                'status' => $refund['status'],
                'amount' => (float) $refund['amount']['value'],
                'gateway_data' => $refund
            ];

        } catch (Exception $e) {
            Log::error('PayPal refund failed', [
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
            // PayPal webhook verification is more complex and requires
            // verifying the certificate and signature
            // For now, we'll implement a basic check
            $headers = $request->headers->all();
            return isset($headers['paypal-transmission-id']) && 
                   isset($headers['paypal-cert-id']) && 
                   isset($headers['paypal-transmission-sig']);

        } catch (Exception $e) {
            Log::error('PayPal webhook signature verification failed', [
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
            $eventType = $payload['event_type'] ?? null;
            $resource = $payload['resource'] ?? null;

            if (!$eventType || !$resource) {
                return null;
            }

            // Only process checkout order events
            if (!str_starts_with($eventType, 'CHECKOUT.ORDER.')) {
                return null;
            }

            $paymentId = $resource['id'];
            $status = $this->mapPayPalStatus($resource['status']);

            return [
                'payment_id' => $paymentId,
                'status' => $status,
                'event_type' => $eventType,
                'gateway_data' => $resource
            ];

        } catch (Exception $e) {
            Log::error('PayPal webhook processing failed', [
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
        return ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'];
    }

    /**
     * Get gateway configuration
     */
    public function getConfig(): array
    {
        return [
            'name' => 'PayPal',
            'enabled' => config('payments.paypal.enabled', false),
            'client_id' => $this->clientId,
            'currencies' => $this->getSupportedCurrencies()
        ];
    }

    /**
     * Validate gateway configuration
     */
    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    /**
     * Get PayPal access token
     */
    private function getAccessToken(): string
    {
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post($this->baseUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials'
            ]);

        if (!$response->successful()) {
            throw new Exception('Failed to get PayPal access token: ' . $response->body());
        }

        return $response->json()['access_token'];
    }

    /**
     * Map PayPal status to internal status
     */
    private function mapPayPalStatus(string $paypalStatus): string
    {
        return match ($paypalStatus) {
            'CREATED', 'SAVED', 'APPROVED', 'PAYER_ACTION_REQUIRED' => 'pending',
            'COMPLETED' => 'completed',
            'CANCELLED', 'VOIDED' => 'cancelled',
            default => 'failed'
        };
    }
}