<?php

namespace App\Services\PaymentGateway\Gateways;

use App\Services\PaymentGateway\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MercadoPagoGateway implements PaymentGatewayInterface
{
    private string $accessToken;
    private string $publicKey;
    private string $baseUrl;
    private string $webhookSecret;

    public function __construct()
    {
        $this->accessToken = config('payments.mercadopago.access_token');
        $this->publicKey = config('payments.mercadopago.public_key');
        $this->baseUrl = config('payments.mercadopago.base_url');
        $this->webhookSecret = config('payments.mercadopago.webhook_secret');
    }

    /**
     * Create a payment intent
     */
    public function createPaymentIntent(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/v1/payments', [
                'transaction_amount' => (float) $data['amount'],
                'description' => 'Order #' . $data['order_id'],
                'payment_method_id' => 'visa', // Default, can be changed
                'payer' => [
                    'email' => $data['customer']['email'],
                    'first_name' => $data['customer']['first_name'] ?? '',
                    'last_name' => $data['customer']['last_name'] ?? ''
                ],
                'external_reference' => 'ORDER_' . $data['order_id'],
                'notification_url' => $data['webhook_url'] ?? config('app.url') . '/api/payments/webhook/mercadopago',
                'back_urls' => [
                    'success' => $data['return_url'] ?? config('app.url') . '/payment/success',
                    'failure' => $data['cancel_url'] ?? config('app.url') . '/payment/failed',
                    'pending' => $data['return_url'] ?? config('app.url') . '/payment/pending'
                ],
                'auto_return' => 'approved'
            ]);

            if (!$response->successful()) {
                throw new Exception('MercadoPago API error: ' . $response->body());
            }

            $payment = $response->json();

            return [
                'payment_id' => $payment['id'],
                'payment_url' => $payment['point_of_interaction']['transaction_data']['ticket_url'] ?? null,
                'status' => $this->mapMercadoPagoStatus($payment['status']),
                'gateway_data' => $payment
            ];

        } catch (Exception $e) {
            Log::error('MercadoPago payment intent creation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Create a preference for checkout
     */
    public function createPreference(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/checkout/preferences', [
                'items' => [
                    [
                        'title' => 'Order #' . $data['order_id'],
                        'quantity' => 1,
                        'unit_price' => (float) $data['amount'],
                        'currency_id' => $data['currency']
                    ]
                ],
                'payer' => [
                    'email' => $data['customer']['email'],
                    'name' => $data['customer']['first_name'] ?? '',
                    'surname' => $data['customer']['last_name'] ?? ''
                ],
                'external_reference' => 'ORDER_' . $data['order_id'],
                'notification_url' => $data['webhook_url'] ?? config('app.url') . '/api/payments/webhook/mercadopago',
                'back_urls' => [
                    'success' => $data['return_url'] ?? config('app.url') . '/payment/success',
                    'failure' => $data['cancel_url'] ?? config('app.url') . '/payment/failed',
                    'pending' => $data['return_url'] ?? config('app.url') . '/payment/pending'
                ],
                'auto_return' => 'approved'
            ]);

            if (!$response->successful()) {
                throw new Exception('MercadoPago API error: ' . $response->body());
            }

            $preference = $response->json();

            return [
                'preference_id' => $preference['id'],
                'payment_url' => $preference['init_point'],
                'sandbox_url' => $preference['sandbox_init_point'] ?? null,
                'gateway_data' => $preference
            ];

        } catch (Exception $e) {
            Log::error('MercadoPago preference creation failed', [
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
                'Authorization' => 'Bearer ' . $this->accessToken
            ])->get($this->baseUrl . '/v1/payments/' . $paymentId);

            if (!$response->successful()) {
                throw new Exception('MercadoPago API error: ' . $response->body());
            }

            $payment = $response->json();

            return [
                'status' => $this->mapMercadoPagoStatus($payment['status']),
                'gateway_data' => $payment
            ];

        } catch (Exception $e) {
            Log::error('MercadoPago payment confirmation failed', [
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
                'Authorization' => 'Bearer ' . $this->accessToken
            ])->get($this->baseUrl . '/v1/payments/' . $paymentId);

            if (!$response->successful()) {
                throw new Exception('MercadoPago API error: ' . $response->body());
            }

            $payment = $response->json();

            return [
                'status' => $this->mapMercadoPagoStatus($payment['status']),
                'gateway_data' => $payment
            ];

        } catch (Exception $e) {
            Log::error('MercadoPago payment status retrieval failed', [
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
            $refundData = [];
            if ($amount !== null) {
                $refundData['amount'] = $amount;
            }
            if ($reason !== null) {
                $refundData['reason'] = $reason;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/v1/payments/' . $paymentId . '/refunds', $refundData);

            if (!$response->successful()) {
                throw new Exception('MercadoPago API error: ' . $response->body());
            }

            $refund = $response->json();

            return [
                'refund_id' => $refund['id'],
                'status' => $refund['status'],
                'amount' => $refund['amount'],
                'gateway_data' => $refund
            ];

        } catch (Exception $e) {
            Log::error('MercadoPago refund failed', [
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
            // MercadoPago uses different verification method
            $xSignature = $request->header('x-signature');
            $xRequestId = $request->header('x-request-id');
            
            if (!$xSignature || !$xRequestId) {
                return false;
            }

            // Extract ts and v1 from x-signature
            $parts = explode(',', $xSignature);
            $ts = null;
            $hash = null;

            foreach ($parts as $part) {
                $keyValue = explode('=', $part, 2);
                if (count($keyValue) === 2) {
                    if ($keyValue[0] === 'ts') {
                        $ts = $keyValue[1];
                    } elseif ($keyValue[0] === 'v1') {
                        $hash = $keyValue[1];
                    }
                }
            }

            if (!$ts || !$hash) {
                return false;
            }

            $manifest = "id:{$xRequestId};request-id:{$xRequestId};ts:{$ts};";
            $expectedHash = hash_hmac('sha256', $manifest, $this->webhookSecret);

            return hash_equals($expectedHash, $hash);

        } catch (Exception $e) {
            Log::error('MercadoPago webhook signature verification failed', [
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
            $type = $payload['type'] ?? null;
            $data = $payload['data'] ?? null;

            if (!$type || !$data) {
                return null;
            }

            // Only process payment events
            if ($type !== 'payment') {
                return null;
            }

            $paymentId = $data['id'];
            
            // Get full payment details
            $paymentDetails = $this->getPaymentStatus($paymentId);

            return [
                'payment_id' => $paymentId,
                'status' => $paymentDetails['status'],
                'event_type' => $type,
                'gateway_data' => $paymentDetails['gateway_data']
            ];

        } catch (Exception $e) {
            Log::error('MercadoPago webhook processing failed', [
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
        return ['ARS', 'BRL', 'CLP', 'COP', 'MXN', 'PEN', 'UYU', 'USD'];
    }

    /**
     * Get gateway configuration
     */
    public function getConfig(): array
    {
        return [
            'name' => 'MercadoPago',
            'enabled' => config('payments.mercadopago.enabled', false),
            'public_key' => $this->publicKey,
            'currencies' => $this->getSupportedCurrencies()
        ];
    }

    /**
     * Validate gateway configuration
     */
    public function isConfigured(): bool
    {
        return !empty($this->accessToken) && !empty($this->publicKey);
    }

    /**
     * Map MercadoPago status to internal status
     */
    private function mapMercadoPagoStatus(string $mpStatus): string
    {
        return match ($mpStatus) {
            'pending', 'in_process', 'in_mediation' => 'pending',
            'approved' => 'completed',
            'rejected', 'cancelled' => 'failed',
            'refunded', 'charged_back' => 'refunded',
            default => 'pending'
        };
    }
}