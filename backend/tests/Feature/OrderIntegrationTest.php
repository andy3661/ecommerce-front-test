<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\Order;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class OrderIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
    }

    public function test_authenticated_user_can_create_order_from_cart()
    {
        $user = User::factory()->create();
        $product1 = Product::factory()->create(['price' => 50.00]);
        $product2 = Product::factory()->create(['price' => 75.00]);
        $address = UserAddress::factory()->create(['user_id' => $user->id]);
        
        // Add items to cart
        Cart::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product1->id,
            'quantity' => 2,
            'unit_price' => 50.00
        ]);
        
        Cart::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product2->id,
            'quantity' => 1,
            'unit_price' => 75.00
        ]);
        
        Sanctum::actingAs($user);

        $orderData = [
            'shipping_address_id' => $address->id,
            'billing_address_id' => $address->id,
            'payment_method' => 'credit_card',
            'notes' => 'Please handle with care'
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'id',
                    'order_number',
                    'status',
                    'total_amount',
                    'items' => [
                        '*' => [
                            'product_id',
                            'quantity',
                            'unit_price',
                            'total_price'
                        ]
                    ],
                    'shipping_address',
                    'billing_address'
                ]);

        // Verify order was created
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => 'pending'
        ]);

        // Verify cart was cleared
        $this->assertDatabaseMissing('carts', [
            'user_id' => $user->id
        ]);
    }

    public function test_user_can_view_their_orders()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        
        // Create orders for the user
        Order::factory()->count(3)->create(['user_id' => $user->id]);
        // Create orders for other user
        Order::factory()->count(2)->create(['user_id' => $otherUser->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/orders');

        $response->assertStatus(200)
                ->assertJsonCount(3, 'data')
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'order_number',
                            'status',
                            'total_amount',
                            'created_at'
                        ]
                    ],
                    'meta'
                ]);
    }

    public function test_user_can_view_single_order_details()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status
                ])
                ->assertJsonStructure([
                    'id',
                    'order_number',
                    'status',
                    'total_amount',
                    'items',
                    'shipping_address',
                    'billing_address',
                    'payment_details',
                    'status_history'
                ]);
    }

    public function test_user_cannot_view_other_users_orders()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user1->id]);
        
        Sanctum::actingAs($user2);

        $response = $this->getJson("/api/orders/{$order->id}");
        $response->assertStatus(404);
    }

    public function test_user_can_cancel_pending_order()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending'
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/orders/{$order->id}/cancel", [
            'reason' => 'Changed my mind'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'cancelled'
                ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled'
        ]);
    }

    public function test_user_cannot_cancel_shipped_order()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'shipped'
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['status']);
    }

    public function test_user_can_track_order_shipment()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'shipped',
            'tracking_number' => 'TRACK123456'
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/orders/{$order->id}/tracking");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'tracking_number',
                    'carrier',
                    'status',
                    'estimated_delivery',
                    'tracking_events' => [
                        '*' => [
                            'status',
                            'description',
                            'timestamp',
                            'location'
                        ]
                    ]
                ]);
    }

    public function test_cannot_create_order_with_empty_cart()
    {
        $user = User::factory()->create();
        $address = UserAddress::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $orderData = [
            'shipping_address_id' => $address->id,
            'billing_address_id' => $address->id,
            'payment_method' => 'credit_card'
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['cart']);
    }

    public function test_cannot_create_order_with_invalid_address()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        
        Cart::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id
        ]);
        
        Sanctum::actingAs($user);

        $orderData = [
            'shipping_address_id' => 999, // Non-existent address
            'billing_address_id' => 999,
            'payment_method' => 'credit_card'
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['shipping_address_id', 'billing_address_id']);
    }

    public function test_order_total_calculation_is_correct()
    {
        $user = User::factory()->create();
        $product1 = Product::factory()->create(['price' => 25.50]);
        $product2 = Product::factory()->create(['price' => 15.75]);
        $address = UserAddress::factory()->create(['user_id' => $user->id]);
        
        Cart::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product1->id,
            'quantity' => 2,
            'unit_price' => 25.50
        ]);
        
        Cart::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product2->id,
            'quantity' => 3,
            'unit_price' => 15.75
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'shipping_address_id' => $address->id,
            'billing_address_id' => $address->id,
            'payment_method' => 'credit_card'
        ]);

        $expectedSubtotal = (25.50 * 2) + (15.75 * 3); // 51.00 + 47.25 = 98.25
        
        $response->assertStatus(201);
        $orderData = $response->json();
        
        $this->assertEquals($expectedSubtotal, $orderData['subtotal']);
    }
}