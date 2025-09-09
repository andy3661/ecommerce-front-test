<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Product;
use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    
    /**
     * Get user's orders
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $status = $request->get('status');
        
        $query = Order::where('user_id', Auth::id())
            ->with(['items.product:id,name,slug,images'])
            ->orderBy('created_at', 'desc');
        
        if ($status) {
            $query->where('status', $status);
        }
        
        $orders = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }
    
    /**
     * Get specific order details
     */
    public function show($id)
    {
        $order = Order::where('user_id', Auth::id())
            ->with([
                'items.product:id,name,slug,images,price',
                'user:id,name,email'
            ])
            ->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }
    
    /**
     * Create new order from cart
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipping_address_id' => 'required|exists:user_addresses,id',
            'billing_address_id' => 'nullable|exists:user_addresses,id',
            'payment_method' => 'required|in:credit_card,debit_card,paypal,stripe,bank_transfer',
            'notes' => 'nullable|string|max:500',
            'coupon_code' => 'nullable|string|exists:coupons,code'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Verify addresses belong to user
        $shippingAddress = UserAddress::where('id', $request->shipping_address_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
        
        $billingAddress = $request->billing_address_id 
            ? UserAddress::where('id', $request->billing_address_id)
                ->where('user_id', Auth::id())
                ->firstOrFail()
            : $shippingAddress;
        
        // Get cart items
        $cartItems = Cart::where('user_id', Auth::id())
            ->with('product')
            ->get();
        
        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty'
            ], 400);
        }
        
        // Validate stock availability
        foreach ($cartItems as $item) {
            if (!$item->product->is_active || $item->product->inventory_quantity < $item->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Product '{$item->product->name}' is not available or insufficient stock"
                ], 400);
            }
        }
        
        DB::beginTransaction();
        
        try {
            // Calculate totals
            $subtotal = $cartItems->sum(function ($item) {
                return $item->quantity * $item->unit_price;
            });
            
            $taxRate = 0.10; // 10% tax
            $taxAmount = $subtotal * $taxRate;
            $shippingCost = $this->calculateShippingCost($cartItems, $shippingAddress);
            
            // Apply coupon if provided
            $discountAmount = 0;
            $couponId = null;
            if ($request->coupon_code) {
                $couponResult = $this->applyCoupon($request->coupon_code, $subtotal);
                $discountAmount = $couponResult['discount'];
                $couponId = $couponResult['coupon_id'];
            }
            
            $total = $subtotal + $taxAmount + $shippingCost - $discountAmount;
            
            // Create order
            $order = Order::create([
                'user_id' => Auth::id(),
                'order_number' => $this->generateOrderNumber(),
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $request->payment_method,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'shipping_cost' => $shippingCost,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'currency' => 'USD',
                'notes' => $request->notes,
                'coupon_id' => $couponId,
                'shipping_address' => $shippingAddress->toArray(),
                'billing_address' => $billingAddress->toArray()
            ]);
            
            // Create order items and update inventory
            foreach ($cartItems as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->unit_price,
                    'total_price' => $cartItem->quantity * $cartItem->unit_price,
                    'product_snapshot' => $cartItem->product->toArray(),
                    'variant_options' => $cartItem->variant_options
                ]);
                
                // Update product inventory
                $cartItem->product->decrement('inventory_quantity', $cartItem->quantity);
            }
            
            // Clear cart
            Cart::where('user_id', Auth::id())->delete();
            
            DB::commit();
            
            // Load order with relationships
            $order->load(['items.product:id,name,slug,images']);
            
            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Cancel an order
     */
    public function cancel($id)
    {
        $order = Order::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();
        
        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be cancelled at this stage'
            ], 400);
        }
        
        DB::beginTransaction();
        
        try {
            // Restore inventory
            foreach ($order->items as $item) {
                $item->product->increment('inventory_quantity', $item->quantity);
            }
            
            // Update order status
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => 'Cancelled by customer'
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'data' => $order
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get order tracking information
     */
    public function tracking($id)
    {
        $order = Order::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();
        
        $trackingSteps = [
            [
                'status' => 'pending',
                'title' => 'Order Placed',
                'description' => 'Your order has been placed successfully',
                'completed' => true,
                'date' => $order->created_at
            ],
            [
                'status' => 'confirmed',
                'title' => 'Order Confirmed',
                'description' => 'Your order has been confirmed and is being prepared',
                'completed' => in_array($order->status, ['confirmed', 'processing', 'shipped', 'delivered']),
                'date' => $order->confirmed_at
            ],
            [
                'status' => 'processing',
                'title' => 'Processing',
                'description' => 'Your order is being processed',
                'completed' => in_array($order->status, ['processing', 'shipped', 'delivered']),
                'date' => $order->processing_at
            ],
            [
                'status' => 'shipped',
                'title' => 'Shipped',
                'description' => 'Your order has been shipped',
                'completed' => in_array($order->status, ['shipped', 'delivered']),
                'date' => $order->shipped_at
            ],
            [
                'status' => 'delivered',
                'title' => 'Delivered',
                'description' => 'Your order has been delivered',
                'completed' => $order->status === 'delivered',
                'date' => $order->delivered_at
            ]
        ];
        
        return response()->json([
            'success' => true,
            'data' => [
                'order' => $order,
                'tracking_steps' => $trackingSteps,
                'tracking_number' => $order->tracking_number,
                'carrier' => $order->shipping_carrier
            ]
        ]);
    }
    
    /**
     * Generate unique order number
     */
    private function generateOrderNumber()
    {
        do {
            $orderNumber = 'ORD-' . date('Y') . '-' . strtoupper(Str::random(8));
        } while (Order::where('order_number', $orderNumber)->exists());
        
        return $orderNumber;
    }
    
    /**
     * Calculate shipping cost
     */
    private function calculateShippingCost($cartItems, $shippingAddress)
    {
        // Simple shipping calculation - can be enhanced with real shipping APIs
        $totalWeight = $cartItems->sum(function ($item) {
            return $item->quantity * ($item->product->weight ?? 1);
        });
        
        $baseShipping = 5.99;
        $weightShipping = $totalWeight * 0.5;
        
        return $baseShipping + $weightShipping;
    }
    
    /**
     * Apply coupon discount
     */
    private function applyCoupon($couponCode, $subtotal)
    {
        // Simple coupon logic - can be enhanced
        $coupon = \App\Models\Coupon::where('code', $couponCode)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>=', now())
            ->first();
        
        if (!$coupon) {
            return ['discount' => 0, 'coupon_id' => null];
        }
        
        $discount = 0;
        
        if ($coupon->type === 'percentage') {
            $discount = $subtotal * ($coupon->value / 100);
            if ($coupon->max_discount_amount) {
                $discount = min($discount, $coupon->max_discount_amount);
            }
        } else {
            $discount = min($coupon->value, $subtotal);
        }
        
        return ['discount' => $discount, 'coupon_id' => $coupon->id];
    }
}