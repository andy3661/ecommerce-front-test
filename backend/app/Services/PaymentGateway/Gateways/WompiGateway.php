<?php

namespace App\Services\PaymentGateway\Gateways;

use App\Services\PaymentGateway\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WompiGateway implements PaymentGatewayInterface
{
    private string $publicKey;
    private string $privateKey;
    private string $baseUrl;
    private string $webhookSecret;

    public function __construct()
    {
        $this->publicKey = config('payments.wompi.public_key');
        $this->privateKey = config('payments.wompi.private_key');
        $this->baseUrl = config('payments.wompi.base_url');
        $this->webhookSecret = config('payments.wompi.webhook_secret');
    }

    /**
     * Create a payment intent
     */
    public function createPaymentIntent(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->privateKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/v1/transactions', [
                'amount_in_cents' => (int) ($data['amount'] * 100),
                'currency' => $data['currency'],
                'customer_email' => $data['customer']['email'],
                'reference' => 'ORDER_' . $data['order_id'],
                'redirect_url' => $data['return_url'] ?? config('app.url') . '/payment/success',
                'payment_method' => [
                    'type' => 'CARD'
                ]
            ]);

            if (!$response->successful()) {
                throw new Exception('Wompi API error: ' . $response->body());
            }

            $transaction = $response->json();

            return [
                'payment_id' => $transaction['data']['id'],
                'payment_url' => $transaction['data']['payment_link_url'] ?? null,
                'status' => $this->mapWompiStatus($transaction['data']['status']),
                'gateway_data' => $transaction['data']
            ];

        } catch (Exception $e) {
            Log::error('Wompi payment intent creation failed', [
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
                'Authorization' => 'Bearer ' . $this->privateKey
            ])->get($this->baseUrl . '/v1/transactions/' . $paymentId);

            if (!$response->successful()) {
                throw new Exception('Wompi API error: ' . $response->body());
            }

            $transaction = $response->json();

            return [
                'status' => $this->mapWompiStatus($transaction['data']['status']),
                'gateway_data' => $transaction['data']
            ];

        } catch (Exception $e) {
            Log::error('Wompi payment confirmation failed', [
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
                'Authorization' => 'Bearer ' . $this->privateKey
            ])->get($this->baseUrl . '/v1/transactions/' . $paymentId);

            if (!$response->successful()) {
                throw new Exception('Wompi API error: ' . $response->body());
            }

            $transaction = $response->json();

            return [
                'status' => $this->mapWompiStatus($transaction['data']['status']),
                'gateway_data' => $transaction['data']
            ];

        } catch (Exception $e) {
            Log::error('Wompi payment status retrieval failed', [
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
            // Wompi doesn't have a direct refund API in their basic implementation
            // This would need to be handled manually or through their dashboard
            throw new Exception('Refunds must be processed manually through Wompi dashboard');

        } catch (Exception $e) {
            Log::error('Wompi refund failed', [
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
            $signature = $request->header('X-Wompi-Signature');

            if (!$signature || !$this->webhookSecret) {
                return false;
            }

            $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

            return hash_equals($expectedSignature, $signature);

        } catch (Exception $e) {
            Log::error('Wompi webhook signature verification failed', [
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
            $event = $payload['event'] ?? null;
            $data = $payload['data'] ?? null;

            if (!$event || !$data) {
                return null;
            }

            // Only process transaction events
            if ($event !== 'transaction.updated') {
                return null;
            }

            $paymentId = $data['transaction']['id'];
            $status = $this->mapWompiStatus($data['transaction']['status']);

            return [
                'payment_id' => $paymentId,
                'status' => $status,
                'event_type' => $event,
                'gateway_data' => $data['transaction']
            ];

        } catch (Exception $e) {
            Log::error('Wompi webhook processing failed', [
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
        return ['COP'];
    }

    /**
     * Get gateway configuration
     */
    public function getConfig(): array
    {
        return [
            'name' => 'Wompi',
            'enabled' => config('payments.wompi.enabled', false),
            'public_key' => $this->publicKey,
            'currencies' => $this->getSupportedCurrencies()
        ];
    }

    /**
     * Validate gateway configuration
     */
    public function isConfigured(): bool
    {
        return !empty($this->publicKey) && !empty($this->privateKey);
    }

    /**
     * Map Wompi status to internal status
     */
    private function mapWompiStatus(string $wompiStatus): string
    {
        return match ($wompiStatus) {
            'PENDING' => 'pending',
            'APPROVED' => 'completed',
            'DECLINED', 'VOIDED' => 'failed',
            'ERROR' => 'failed',
            default => 'pending'
        };
    }
}