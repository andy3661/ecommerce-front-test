<?php

use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('order can be created with valid data', function () {
    $user = User::factory()->create();
    
    $orderData = [
        'user_id' => $user->id,
        'order_number' => 'ORD-001',
        'status' => 'pending',
        'total_amount' => 199.98,
        'subtotal' => 179.98,
        'tax_amount' => 20.00,
        'shipping_amount' => 0.00,
        'discount_amount' => 0.00,
        'currency' => 'USD',
        'billing_address' => json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line_1' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US'
        ]),
        'shipping_address' => json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line_1' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US'
        ])
    ];

    $order = Order::create($orderData);

    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->order_number)->toBe('ORD-001')
        ->and($order->status)->toBe('pending')
        ->and($order->total_amount)->toBe(199.98)
        ->and($order->user_id)->toBe($user->id);
});

test('order number must be unique', function () {
    Order::factory()->create(['order_number' => 'ORD-UNIQUE']);

    expect(function () {
        Order::factory()->create(['order_number' => 'ORD-UNIQUE']);
    })->toThrow(Exception::class);
});

test('order belongs to user', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $order = Order::factory()->create(['user_id' => $user->id]);

    expect($order->user)->toBeInstanceOf(User::class)
        ->and($order->user->name)->toBe('John Doe');
});

test('order can have items', function () {
    $order = Order::factory()->create();
    $product1 = Product::factory()->create(['price' => 50.00]);
    $product2 = Product::factory()->create(['price' => 75.00]);
    
    $order->items()->createMany([
        [
            'product_id' => $product1->id,
            'quantity' => 2,
            'unit_price' => 50.00,
            'total_price' => 100.00
        ],
        [
            'product_id' => $product2->id,
            'quantity' => 1,
            'unit_price' => 75.00,
            'total_price' => 75.00
        ]
    ]);

    expect($order->items)->toHaveCount(2)
        ->and($order->items->sum('total_price'))->toBe(175.00);
});

test('order can have payments', function () {
    $order = Order::factory()->create();
    
    $order->payments()->create([
        'payment_method' => 'stripe',
        'amount' => 99.99,
        'currency' => 'USD',
        'status' => 'completed',
        'transaction_id' => 'txn_123456',
        'gateway_response' => json_encode(['status' => 'success'])
    ]);

    expect($order->payments)->toHaveCount(1)
        ->and($order->payments->first()->amount)->toBe(99.99)
        ->and($order->payments->first()->status)->toBe('completed');
});

test('order can have status history', function () {
    $order = Order::factory()->create(['status' => 'pending']);
    
    $order->statusHistory()->create([
        'status' => 'processing',
        'notes' => 'Order is being processed',
        'changed_by' => null
    ]);

    expect($order->statusHistory)->toHaveCount(1)
        ->and($order->statusHistory->first()->status)->toBe('processing');
});

test('order calculates total items correctly', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create();
    
    $order->items()->createMany([
        ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 10.00, 'total_price' => 30.00],
        ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 15.00, 'total_price' => 30.00]
    ]);

    expect($order->total_items)->toBe(5);
});

test('order can check if paid', function () {
    $paidOrder = Order::factory()->create();
    $unpaidOrder = Order::factory()->create();
    
    $paidOrder->payments()->create([
        'amount' => $paidOrder->total_amount,
        'status' => 'completed',
        'payment_method' => 'stripe',
        'currency' => 'USD'
    ]);

    expect($paidOrder->isPaid())->toBeTrue()
        ->and($unpaidOrder->isPaid())->toBeFalse();
});

test('order can check if completed', function () {
    $completedOrder = Order::factory()->create(['status' => 'completed']);
    $pendingOrder = Order::factory()->create(['status' => 'pending']);

    expect($completedOrder->isCompleted())->toBeTrue()
        ->and($pendingOrder->isCompleted())->toBeFalse();
});

test('order can check if cancelled', function () {
    $cancelledOrder = Order::factory()->create(['status' => 'cancelled']);
    $activeOrder = Order::factory()->create(['status' => 'processing']);

    expect($cancelledOrder->isCancelled())->toBeTrue()
        ->and($activeOrder->isCancelled())->toBeFalse();
});

test('order formatted total works correctly', function () {
    $order = Order::factory()->create(['total_amount' => 199.99, 'currency' => 'USD']);

    expect($order->formatted_total)->toBe('$199.99');
});

test('order can update status with history', function () {
    $order = Order::factory()->create(['status' => 'pending']);
    
    $order->updateStatus('processing', 'Order is being processed');
    
    expect($order->fresh()->status)->toBe('processing')
        ->and($order->statusHistory)->toHaveCount(1)
        ->and($order->statusHistory->first()->status)->toBe('processing');
});

test('order scope for status works', function () {
    Order::factory()->create(['status' => 'pending']);
    Order::factory()->create(['status' => 'completed']);
    Order::factory()->create(['status' => 'pending']);

    $pendingOrders = Order::whereStatus('pending')->get();

    expect($pendingOrders)->toHaveCount(2);
});

test('order can be soft deleted', function () {
    $order = Order::factory()->create();
    $orderId = $order->id;

    $order->delete();

    expect(Order::find($orderId))->toBeNull()
        ->and(Order::withTrashed()->find($orderId))->not->toBeNull();
});