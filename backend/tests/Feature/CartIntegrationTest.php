<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class CartIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
    }

    public function test_authenticated_user_can_add_product_to_cart()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 99.99]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 2
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'id',
                    'product_id',
                    'quantity',
                    'unit_price',
                    'product' => [
                        'id',
                        'name',
                        'price'
                    ]
                ]);

        $this->assertDatabaseHas('carts', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2
        ]);
    }

    public function test_user_can_view_cart_items()
    {
        $user = User::factory()->create();
        $product1 = Product::factory()->create(['price' => 50.00]);
        $product2 = Product::factory()->create(['price' => 75.00]);
        
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

        $response = $this->getJson('/api/cart');

        $response->assertStatus(200)
                ->assertJsonCount(2, 'data')
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'product_id',
                            'quantity',
                            'unit_price',
                            'product'
                        ]
                    ],
                    'total',
                    'count'
                ]);
    }

    public function test_user_can_update_cart_item_quantity()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cartItem = Cart::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/cart/{$cartItem->id}", [
            'quantity' => 5
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'quantity' => 5
                ]);

        $this->assertDatabaseHas('carts', [
            'id' => $cartItem->id,
            'quantity' => 5
        ]);
    }

    public function test_user_can_remove_item_from_cart()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cartItem = Cart::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/cart/{$cartItem->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Item removed from cart'
                ]);

        $this->assertDatabaseMissing('carts', [
            'id' => $cartItem->id
        ]);
    }

    public function test_user_can_clear_entire_cart()
    {
        $user = User::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        
        Cart::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product1->id
        ]);
        
        Cart::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product2->id
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/cart');

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Cart cleared'
                ]);

        $this->assertDatabaseMissing('carts', [
            'user_id' => $user->id
        ]);
    }

    public function test_user_can_get_cart_count()
    {
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
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/cart/count');

        $response->assertStatus(200)
                ->assertJson([
                    'count' => 5 // 2 + 3
                ]);
    }

    public function test_user_cannot_add_out_of_stock_product_to_cart()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'inventory_quantity' => 0,
            'track_inventory' => true
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['quantity']);
    }

    public function test_user_cannot_access_other_users_cart()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $product = Product::factory()->create();
        
        $cartItem = Cart::factory()->create([
            'user_id' => $user1->id,
            'product_id' => $product->id
        ]);
        
        Sanctum::actingAs($user2);

        $response = $this->deleteJson("/api/cart/{$cartItem->id}");
        $response->assertStatus(404);

        $response = $this->putJson("/api/cart/{$cartItem->id}", ['quantity' => 5]);
        $response->assertStatus(404);
    }
}