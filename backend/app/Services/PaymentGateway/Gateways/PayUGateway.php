<?php

namespace App\Services\PaymentGateway\Gateways;

use App\Services\PaymentGateway\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PayUGateway implements PaymentGatewayInterface
{
    private string $apiKey;
    private string $apiLogin;
    private string $merchantId;
    private string $accountId;
    private string $baseUrl;
    private string $webhookSecret;
    private bool $testMode;

    public function __construct()
    {
        $this->apiKey = config('payments.payu.api_key');
        $this->apiLogin = config('payments.payu.api_login');
        $this->merchantId = config('payments.payu.merchant_id');
        $this->accountId = config('payments.payu.account_id');
        $this->baseUrl = config('payments.payu.base_url');
        $this->webhookSecret = config('payments.payu.webhook_secret');
        $this->testMode = config('payments.payu.test_mode', true);
    }

    /**
     * Create a payment intent
     */
    public function createPaymentIntent(array $data): array
    {
        try {
            $reference = 'ORDER_' . $data['order_id'] . '_' . time();
            $signature = $this->generateSignature(
                $this->apiKey,
                $this->merchantId,
                $reference,
                $data['amount'],
                $data['currency']
            );

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->baseUrl . '/payments-api/4.0/service.cgi', [
                'language' => 'es',
                'command' => 'SUBMIT_TRANSACTION',
                'merchant' => [
                    'apiKey' => $this->apiKey,
                    'apiLogin' => $this->apiLogin
                ],
                'transaction' => [
                    'order' => [
                        'accountId' => $this->accountId,
                        'referenceCode' => $reference,
                        'description' => 'Order #' . $data['order_id'],
                        'language' => 'es',
                        'signature' => $signature,
                        'notifyUrl' => $data['webhook_url'] ?? config('app.url') . '/api/payments/webhook/payu',
                        'additionalValues' => [
                            'TX_VALUE' => [
                                'value' => $data['amount'],
                                'currency' => $data['currency']
                            ]
                        ],
                        'buyer' => [
                            'merchantBuyerId' => $data['customer']['id'] ?? '1',
                            'fullName' => ($data['customer']['first_name'] ?? '') . ' ' . ($data['customer']['last_name'] ?? ''),
                            'emailAddress' => $data['customer']['email'],
                            'contactPhone' => $data['customer']['phone'] ?? '',
                            'dniNumber' => $data['customer']['document'] ?? '',
                            'shippingAddress' => [
                                'street1' => $data['customer']['address'] ?? '',
                                'city' => $data['customer']['city'] ?? '',
                                'state' => $data['customer']['state'] ?? '',
                                'country' => $data['customer']['country'] ?? 'CO',
                                'postalCode' => $data['customer']['postal_code'] ?? ''
                            ]
                        ]
                    ],
                    'payer' => [
                        'merchantPayerId' => $data['customer']['id'] ?? '1',
                        'fullName' => ($data['customer']['first_name'] ?? '') . ' ' . ($data['customer']['last_name'] ?? ''),
                        'emailAddress' => $data['customer']['email'],
                        'contactPhone' => $data['customer']['phone'] ?? '',
                        'dniNumber' => $data['customer']['document'] ?? '',
                        'billingAddress' => [
                            'street1' => $data['customer']['address'] ?? '',
                            'city' => $data['customer']['city'] ?? '',
                            'state' => $data['customer']['state'] ?? '',
                            'country' => $data['customer']['country'] ?? 'CO',
                            'postalCode' => $data['customer']['postal_code'] ?? ''
                        ]
                    ],
                    'creditCard' => [
                        'number' => $data['card']['number'] ?? '',
                        'securityCode' => $data['card']['cvc'] ?? '',
                        'expirationDate' => ($data['card']['exp_year'] ?? '') . '/' . str_pad($data['card']['exp_month'] ?? '', 2, '0', STR_PAD_LEFT),
                        'name' => $data['card']['name'] ?? ($data['customer']['first_name'] ?? '') . ' ' . ($data['customer']['last_name'] ?? '')
                    ],
                    'extraParameters' => [
                        'INSTALLMENTS_NUMBER' => 1
                    ],
                    'type' => 'AUTHORIZATION_AND_CAPTURE',
                    'paymentMethod' => $data['payment_method'] ?? 'VISA',
                    'paymentCountry' => $data['customer']['country'] ?? 'CO',
                    'deviceSessionId' => $data['device_session_id'] ?? uniqid(),
                    'ipAddress' => $data['ip_address'] ?? '127.0.0.1',
                    'cookie' => 'pt1t38347bs6jc9ruv2ecpv7o2',
                    'userAgent' => 'Mozilla/5.0 (Windows NT 5.1; rv:18.0) Gecko/20100101 Firefox/18.0'
                ],
                'test' => $this->testMode
            ]);

            if (!$response->successful()) {
                throw new Exception('PayU API error: ' . $response->body());
            }

            $result = $response->json();

            if ($result['code'] !== 'SUCCESS') {
                throw new Exception('PayU transaction failed: ' . ($result['error'] ?? 'Unknown error'));
            }

            $transaction = $result['transactionResponse'];

            return [
                'payment_id' => $transaction['transactionId'],
                'order_id' => $transaction['orderId'],
                'status' => $this->mapPayUStatus($transaction['state']),
                'response_code' => $transaction['responseCode'],
                'gateway_data' => $transaction
            ];

        } catch (Exception $e) {
            Log::error('PayU payment intent creation failed', [
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
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->baseUrl . '/payments-api/4.0/service.cgi', [
                'language' => 'es',
                'command' => 'ORDER_DETAIL_BY_REFERENCE_CODE',
                'merchant' => [
                    'apiKey' => $this->apiKey,
                    'apiLogin' => $this->apiLogin
                ],
                'details' => [
                    'referenceCode' => $data['reference'] ?? $paymentId
                ],
                'test' => $this->testMode
            ]);

            if (!$response->successful()) {
                throw new Exception('PayU API error: ' . $response->body());
            }

            $result = $response->json();

            if ($result['code'] !== 'SUCCESS') {
                throw new Exception('PayU query failed: ' . ($result['error'] ?? 'Unknown error'));
            }

            $order = $result['result']['payload'][0] ?? null;
            if (!$order) {
                throw new Exception('Order not found');
            }

            $transaction = $order['transactions'][0] ?? null;
            if (!$transaction) {
                throw new Exception('Transaction not found');
            }

            return [
                'status' => $this->mapPayUStatus($transaction['transactionResponse']['state']),
                'gateway_data' => $transaction
            ];

        } catch (Exception $e) {
            Log::error('PayU payment confirmation failed', [
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
        return $this->confirmPayment($paymentId);
    }

    /**
     * Refund a payment
     */
    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->baseUrl . '/payments-api/4.0/service.cgi', [
                'language' => 'es',
                'command' => 'SUBMIT_TRANSACTION',
                'merchant' => [
                    'apiKey' => $this->apiKey,
                    'apiLogin' => $this->apiLogin
                ],
                'transaction' => [
                    'order' => [
                        'id' => $paymentId
                    ],
                    'type' => 'REFUND',
                    'reason' => $reason ?? 'Customer request',
                    'parentTransactionId' => $paymentId
                ],
                'test' => $this->testMode
            ]);

            if (!$response->successful()) {
                throw new Exception('PayU API error: ' . $response->body());
            }

            $result = $response->json();

            if ($result['code'] !== 'SUCCESS') {
                throw new Exception('PayU refund failed: ' . ($result['error'] ?? 'Unknown error'));
            }

            $transaction = $result['transactionResponse'];

            return [
                'refund_id' => $transaction['transactionId'],
                'status' => $this->mapPayUStatus($transaction['state']),
                'gateway_data' => $transaction
            ];

        } catch (Exception $e) {
            Log::error('PayU refund failed', [
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
            $signature = $request->input('signature');
            $referenceCode = $request->input('reference_sale');
            $txValue = $request->input('value');
            $currency = $request->input('currency');
            $state = $request->input('state_pol');

            if (!$signature || !$referenceCode) {
                return false;
            }

            $expectedSignature = $this->generateSignature(
                $this->apiKey,
                $this->merchantId,
                $referenceCode,
                $txValue,
                $currency,
                $state
            );

            return hash_equals($expectedSignature, $signature);

        } catch (Exception $e) {
            Log::error('PayU webhook signature verification failed', [
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
            $referenceCode = $payload['reference_sale'] ?? null;
            $state = $payload['state_pol'] ?? null;
            $transactionId = $payload['transaction_id'] ?? null;

            if (!$referenceCode || !$state || !$transactionId) {
                return null;
            }

            return [
                'payment_id' => $transactionId,
                'reference' => $referenceCode,
                'status' => $this->mapPayUPolStatus($state),
                'event_type' => 'payment.updated',
                'gateway_data' => $payload
            ];

        } catch (Exception $e) {
            Log::error('PayU webhook processing failed', [
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
        return ['COP', 'USD', 'PEN', 'MXN', 'ARS', 'BRL'];
    }

    /**
     * Get gateway configuration
     */
    public function getConfig(): array
    {
        return [
            'name' => 'PayU',
            'enabled' => config('payments.payu.enabled', false),
            'merchant_id' => $this->merchantId,
            'currencies' => $this->getSupportedCurrencies(),
            'test_mode' => $this->testMode
        ];
    }

    /**
     * Validate gateway configuration
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiLogin) && !empty($this->merchantId) && !empty($this->accountId);
    }

    /**
     * Generate PayU signature
     */
    private function generateSignature(string $apiKey, string $merchantId, string $referenceCode, float $amount, string $currency, ?string $state = null): string
    {
        $signature = $apiKey . '~' . $merchantId . '~' . $referenceCode . '~' . $amount . '~' . $currency;
        
        if ($state !== null) {
            $signature .= '~' . $state;
        }

        return md5($signature);
    }

    /**
     * Map PayU transaction status to internal status
     */
    private function mapPayUStatus(string $payuStatus): string
    {
        return match ($payuStatus) {
            'APPROVED' => 'completed',
            'DECLINED' => 'failed',
            'PENDING' => 'pending',
            'EXPIRED' => 'failed',
            'ERROR' => 'failed',
            default => 'pending'
        };
    }

    /**
     * Map PayU POL status to internal status
     */
    private function mapPayUPolStatus(string $polStatus): string
    {
        return match ($polStatus) {
            '4' => 'completed', // Approved
            '6' => 'failed',    // Declined
            '104' => 'failed',  // Error
            '7' => 'pending',   // Pending
            '5' => 'failed',    // Expired
            default => 'pending'
        };
    }
}