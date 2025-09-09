<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use App\Services\PaymentGateway\PaymentGatewayInterface;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['webhook']);
    }

    /**
     * Get available payment methods
     */
    public function methods(Request $request): JsonResponse
    {
        try {
            $methods = [
                [
                    'id' => 'stripe',
                    'name' => 'Stripe',
                    'type' => 'card',
                    'description' => 'Pay with credit or debit card',
                    'enabled' => config('payments.stripe.enabled', false),
                    'currencies' => ['USD', 'EUR'],
                    'icon' => '/images/payment-methods/stripe.svg'
                ],
                [
                    'id' => 'paypal',
                    'name' => 'PayPal',
                    'type' => 'wallet',
                    'description' => 'Pay with your PayPal account',
                    'enabled' => config('payments.paypal.enabled', false),
                    'currencies' => ['USD', 'EUR'],
                    'icon' => '/images/payment-methods/paypal.svg'
                ],
                [
                    'id' => 'payu',
                    'name' => 'PayU',
                    'type' => 'gateway',
                    'description' => 'Pay with PayU gateway',
                    'enabled' => config('payments.payu.enabled', false),
                    'currencies' => ['COP', 'USD'],
                    'icon' => '/images/payment-methods/payu.svg'
                ],
                [
                    'id' => 'wompi',
                    'name' => 'Wompi',
                    'type' => 'gateway',
                    'description' => 'Pay with Wompi gateway',
                    'enabled' => config('payments.wompi.enabled', false),
                    'currencies' => ['COP'],
                    'icon' => '/images/payment-methods/wompi.svg'
                ],
                [
                    'id' => 'mercadopago',
                    'name' => 'MercadoPago',
                    'type' => 'gateway',
                    'description' => 'Pay with MercadoPago',
                    'enabled' => config('payments.mercadopago.enabled', false),
                    'currencies' => ['ARS', 'BRL', 'CLP', 'COP', 'MXN', 'PEN', 'UYU'],
                    'icon' => '/images/payment-methods/mercadopago.svg'
                ]
            ];

            // Filter only enabled methods
            $enabledMethods = array_filter($methods, function ($method) {
                return $method['enabled'];
            });

            return response()->json([
                'success' => true,
                'data' => array_values($enabledMethods)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment methods',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create payment intent
     */
    public function createIntent(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_id' => 'required|integer|exists:orders,id',
                'payment_method' => 'required|string|in:stripe,paypal,payu,wompi,mercadopago',
                'return_url' => 'nullable|url',
                'cancel_url' => 'nullable|url'
            ]);

            $user = Auth::user();
            $order = Order::where('id', $request->order_id)
                         ->where('user_id', $user->id)
                         ->where('status', 'pending')
                         ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found or not available for payment'
                ], 404);
            }

            // Check if order already has a successful payment
            if ($order->payment_status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Order has already been paid'
                ], 409);
            }

            DB::beginTransaction();

            try {
                // Get payment gateway
                $gateway = PaymentGatewayFactory::create($request->payment_method);
                
                // Create payment record
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'payment_method' => $request->payment_method,
                    'amount' => $order->total_amount,
                    'currency' => $order->currency ?? 'USD',
                    'status' => 'pending',
                    'reference' => 'PAY_' . strtoupper(uniqid())
                ]);

                // Create payment intent with gateway
                $intentData = $gateway->createPaymentIntent([
                    'amount' => $order->total_amount,
                    'currency' => $order->currency ?? 'USD',
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'customer' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'name' => $user->name
                    ],
                    'return_url' => $request->return_url,
                    'cancel_url' => $request->cancel_url,
                    'metadata' => [
                        'order_number' => $order->order_number,
                        'user_id' => $user->id
                    ]
                ]);

                // Update payment with gateway data
                $payment->update([
                    'gateway_payment_id' => $intentData['payment_id'] ?? null,
                    'gateway_data' => $intentData
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'payment_id' => $payment->id,
                        'payment_intent' => $intentData,
                        'order' => [
                            'id' => $order->id,
                            'order_number' => $order->order_number,
                            'total_amount' => $order->total_amount,
                            'currency' => $order->currency ?? 'USD'
                        ]
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Payment intent creation failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'order_id' => $request->order_id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating payment intent',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Confirm payment
     */
    public function confirm(Request $request, $paymentId): JsonResponse
    {
        try {
            $request->validate([
                'gateway_data' => 'nullable|array'
            ]);

            $user = Auth::user();
            $payment = Payment::where('id', $paymentId)
                             ->where('user_id', $user->id)
                             ->with('order')
                             ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            if ($payment->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment has already been completed'
                ], 409);
            }

            DB::beginTransaction();

            try {
                // Get payment gateway
                $gateway = PaymentGatewayFactory::create($payment->payment_method);
                
                // Confirm payment with gateway
                $confirmationData = $gateway->confirmPayment(
                    $payment->gateway_payment_id,
                    $request->gateway_data ?? []
                );

                // Update payment status
                $payment->update([
                    'status' => $confirmationData['status'],
                    'gateway_data' => array_merge(
                        $payment->gateway_data ?? [],
                        $confirmationData
                    ),
                    'completed_at' => $confirmationData['status'] === 'completed' ? now() : null
                ]);

                // Update order if payment is successful
                if ($confirmationData['status'] === 'completed') {
                    $payment->order->update([
                        'payment_status' => 'paid',
                        'status' => 'processing'
                    ]);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'payment_id' => $payment->id,
                        'status' => $payment->status,
                        'order_status' => $payment->order->status,
                        'confirmation_data' => $confirmationData
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Payment confirmation failed', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error confirming payment',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get payment status
     */
    public function status(Request $request, $paymentId): JsonResponse
    {
        try {
            $user = Auth::user();
            $payment = Payment::where('id', $paymentId)
                             ->where('user_id', $user->id)
                             ->with('order')
                             ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $payment->id,
                    'status' => $payment->status,
                    'payment_method' => $payment->payment_method,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'reference' => $payment->reference,
                    'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                    'completed_at' => $payment->completed_at?->format('Y-m-d H:i:s'),
                    'order' => [
                        'id' => $payment->order->id,
                        'order_number' => $payment->order->order_number,
                        'status' => $payment->order->status,
                        'payment_status' => $payment->order->payment_status
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Handle payment webhooks
     */
    public function webhook(Request $request, $provider): JsonResponse
    {
        try {
            Log::info('Payment webhook received', [
                'provider' => $provider,
                'payload' => $request->all(),
                'headers' => $request->headers->all()
            ]);

            // Get payment gateway
            $gateway = PaymentGatewayFactory::create($provider);
            
            // Verify webhook signature
            if (!$gateway->verifyWebhookSignature($request)) {
                Log::warning('Invalid webhook signature', ['provider' => $provider]);
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            // Process webhook
            $webhookData = $gateway->processWebhook($request->all());
            
            if (!$webhookData) {
                return response()->json(['message' => 'Webhook ignored'], 200);
            }

            DB::beginTransaction();

            try {
                // Find payment by gateway payment ID
                $payment = Payment::where('gateway_payment_id', $webhookData['payment_id'])
                                 ->with('order')
                                 ->first();

                if (!$payment) {
                    Log::warning('Payment not found for webhook', [
                        'provider' => $provider,
                        'gateway_payment_id' => $webhookData['payment_id']
                    ]);
                    return response()->json(['error' => 'Payment not found'], 404);
                }

                // Update payment status
                $payment->update([
                    'status' => $webhookData['status'],
                    'gateway_data' => array_merge(
                        $payment->gateway_data ?? [],
                        $webhookData
                    ),
                    'completed_at' => $webhookData['status'] === 'completed' ? now() : null
                ]);

                // Update order status
                if ($webhookData['status'] === 'completed') {
                    $payment->order->update([
                        'payment_status' => 'paid',
                        'status' => 'processing'
                    ]);
                } elseif ($webhookData['status'] === 'failed') {
                    $payment->order->update([
                        'payment_status' => 'failed'
                    ]);
                }

                DB::commit();

                Log::info('Webhook processed successfully', [
                    'provider' => $provider,
                    'payment_id' => $payment->id,
                    'status' => $webhookData['status']
                ]);

                return response()->json(['message' => 'Webhook processed'], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response()->json([
                'error' => 'Webhook processing failed'
            ], 500);
        }
    }
}