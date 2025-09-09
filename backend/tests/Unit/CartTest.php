<?php

use App\Models\Cart;
use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('cart can be created with valid data', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 29.99]);
    
    $cartData = [
        'user_id' => $user->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 29.99,
        'total_price' => 59.98
    ];

    $cart = Cart::create($cartData);

    expect($cart)->toBeInstanceOf(Cart::class)
        ->and($cart->user_id)->toBe($user->id)
        ->and($cart->product_id)->toBe($product->id)
        ->and($cart->quantity)->toBe(2)
        ->and($cart->total_price)->toBe(59.98);
});

test('cart belongs to user', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $cart = Cart::factory()->create(['user_id' => $user->id]);

    expect($cart->user)->toBeInstanceOf(User::class)
        ->and($cart->user->name)->toBe('John Doe');
});

test('cart belongs to product', function () {
    $product = Product::factory()->create(['name' => 'Test Product']);
    $cart = Cart::factory()->create(['product_id' => $product->id]);

    expect($cart->product)->toBeInstanceOf(Product::class)
        ->and($cart->product->name)->toBe('Test Product');
});

test('cart calculates total price correctly', function () {
    $product = Product::factory()->create(['price' => 25.00]);
    $cart = Cart::factory()->create([
        'product_id' => $product->id,
        'quantity' => 3,
        'unit_price' => 25.00
    ]);

    $cart->calculateTotalPrice();

    expect($cart->total_price)->toBe(75.00);
});

test('cart can update quantity', function () {
    $cart = Cart::factory()->create([
        'quantity' => 1,
        'unit_price' => 50.00,
        'total_price' => 50.00
    ]);

    $cart->updateQuantity(3);

    expect($cart->fresh()->quantity)->toBe(3)
        ->and($cart->fresh()->total_price)->toBe(150.00);
});

test('cart prevents duplicate user-product combinations', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    
    Cart::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id
    ]);

    expect(function () use ($user, $product) {
        Cart::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id
        ]);
    })->toThrow(Exception::class);
});

test('cart can get user total cart value', function () {
    $user = User::factory()->create();
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    
    Cart::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product1->id,
        'total_price' => 50.00
    ]);
    
    Cart::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product2->id,
        'total_price' => 75.00
    ]);

    $totalValue = Cart::where('user_id', $user->id)->sum('total_price');

    expect($totalValue)->toBe(125.00);
});

test('cart can get user total items count', function () {
    $user = User::factory()->create();
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    
    Cart::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product1->id,
        'quantity' => 2
    ]);
    
    Cart::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product2->id,
        'quantity' => 3
    ]);

    $totalItems = Cart::where('user_id', $user->id)->sum('quantity');

    expect($totalItems)->toBe(5);
});

test('cart validates quantity is positive', function () {
    expect(function () {
        Cart::factory()->create(['quantity' => -1]);
    })->toThrow(Exception::class);
});

test('cart validates unit price is positive', function () {
    expect(function () {
        Cart::factory()->create(['unit_price' => -10.00]);
    })->toThrow(Exception::class);
});

test('cart can check if product is in user cart', function () {
    $user = User::factory()->create();
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    
    Cart::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product1->id
    ]);

    $isInCart = Cart::where('user_id', $user->id)
                   ->where('product_id', $product1->id)
                   ->exists();
    
    $isNotInCart = Cart::where('user_id', $user->id)
                      ->where('product_id', $product2->id)
                      ->exists();

    expect($isInCart)->toBeTrue()
        ->and($isNotInCart)->toBeFalse();
});

test('cart can be cleared for user', function () {
    $user = User::factory()->create();
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    
    Cart::factory()->create(['user_id' => $user->id, 'product_id' => $product1->id]);
    Cart::factory()->create(['user_id' => $user->id, 'product_id' => $product2->id]);

    Cart::where('user_id', $user->id)->delete();

    $remainingItems = Cart::where('user_id', $user->id)->count();

    expect($remainingItems)->toBe(0);
});

test('cart formatted total price works correctly', function () {
    $cart = Cart::factory()->create(['total_price' => 99.99]);

    expect($cart->formatted_total)->toBe('$99.99');
});

test('cart scope for user works', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    
    Cart::factory()->create(['user_id' => $user1->id]);
    Cart::factory()->create(['user_id' => $user1->id]);
    Cart::factory()->create(['user_id' => $user2->id]);

    $user1CartItems = Cart::where('user_id', $user1->id)->get();

    expect($user1CartItems)->toHaveCount(2);
});