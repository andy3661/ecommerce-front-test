<?php

use App\Models\Product;
use App\Models\Category;
use App\Models\Tag;
use App\Models\ProductReview;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('product can be created with valid data', function () {
    $category = Category::factory()->create();
    
    $productData = [
        'name' => 'Test Product',
        'slug' => 'test-product',
        'description' => 'A test product description',
        'short_description' => 'Short description',
        'sku' => 'TEST-001',
        'price' => 99.99,
        'compare_price' => 129.99,
        'cost_price' => 50.00,
        'stock_quantity' => 100,
        'min_stock_level' => 10,
        'weight' => 1.5,
        'dimensions' => json_encode(['length' => 10, 'width' => 5, 'height' => 3]),
        'status' => 'active',
        'is_featured' => true,
        'category_id' => $category->id,
        'meta_title' => 'Test Product Meta',
        'meta_description' => 'Test product meta description'
    ];

    $product = Product::create($productData);

    expect($product)->toBeInstanceOf(Product::class)
        ->and($product->name)->toBe('Test Product')
        ->and($product->price)->toBe(99.99)
        ->and($product->is_featured)->toBeTrue()
        ->and($product->category_id)->toBe($category->id);
});

test('product slug must be unique', function () {
    Product::factory()->create(['slug' => 'unique-product']);

    expect(function () {
        Product::factory()->create(['slug' => 'unique-product']);
    })->toThrow(Exception::class);
});

test('product sku must be unique', function () {
    Product::factory()->create(['sku' => 'UNIQUE-SKU']);

    expect(function () {
        Product::factory()->create(['sku' => 'UNIQUE-SKU']);
    })->toThrow(Exception::class);
});

test('product belongs to category', function () {
    $category = Category::factory()->create(['name' => 'Electronics']);
    $product = Product::factory()->create(['category_id' => $category->id]);

    expect($product->category)->toBeInstanceOf(Category::class)
        ->and($product->category->name)->toBe('Electronics');
});

test('product can have tags', function () {
    $product = Product::factory()->create();
    $tag1 = Tag::factory()->create(['name' => 'popular']);
    $tag2 = Tag::factory()->create(['name' => 'sale']);

    $product->tags()->attach([$tag1->id, $tag2->id]);

    expect($product->tags)->toHaveCount(2)
        ->and($product->tags->pluck('name')->toArray())->toContain('popular', 'sale');
});

test('product can have reviews', function () {
    $product = Product::factory()->create();
    $user = \App\Models\User::factory()->create();
    
    $product->reviews()->create([
        'user_id' => $user->id,
        'rating' => 5,
        'title' => 'Great product!',
        'comment' => 'Really love this product',
        'status' => 'approved'
    ]);

    expect($product->reviews)->toHaveCount(1)
        ->and($product->reviews->first()->rating)->toBe(5);
});

test('product calculates average rating correctly', function () {
    $product = Product::factory()->create();
    $user1 = \App\Models\User::factory()->create();
    $user2 = \App\Models\User::factory()->create();
    $user3 = \App\Models\User::factory()->create();
    
    $product->reviews()->createMany([
        ['user_id' => $user1->id, 'rating' => 5, 'status' => 'approved'],
        ['user_id' => $user2->id, 'rating' => 4, 'status' => 'approved'],
        ['user_id' => $user3->id, 'rating' => 3, 'status' => 'approved']
    ]);

    expect($product->average_rating)->toBe(4.0);
});

test('product can check if in stock', function () {
    $inStockProduct = Product::factory()->create(['stock_quantity' => 10]);
    $outOfStockProduct = Product::factory()->create(['stock_quantity' => 0]);

    expect($inStockProduct->isInStock())->toBeTrue()
        ->and($outOfStockProduct->isInStock())->toBeFalse();
});

test('product can check if low stock', function () {
    $lowStockProduct = Product::factory()->create([
        'stock_quantity' => 5,
        'min_stock_level' => 10
    ]);
    $normalStockProduct = Product::factory()->create([
        'stock_quantity' => 20,
        'min_stock_level' => 10
    ]);

    expect($lowStockProduct->isLowStock())->toBeTrue()
        ->and($normalStockProduct->isLowStock())->toBeFalse();
});

test('product price formatting works correctly', function () {
    $product = Product::factory()->create(['price' => 99.99]);

    expect($product->formatted_price)->toBe('$99.99');
});

test('product can be soft deleted', function () {
    $product = Product::factory()->create();
    $productId = $product->id;

    $product->delete();

    expect(Product::find($productId))->toBeNull()
        ->and(Product::withTrashed()->find($productId))->not->toBeNull();
});

test('product scope for active products works', function () {
    Product::factory()->create(['status' => 'active']);
    Product::factory()->create(['status' => 'inactive']);
    Product::factory()->create(['status' => 'active']);

    $activeProducts = Product::active()->get();

    expect($activeProducts)->toHaveCount(2);
});

test('product scope for featured products works', function () {
    Product::factory()->create(['is_featured' => true]);
    Product::factory()->create(['is_featured' => false]);
    Product::factory()->create(['is_featured' => true]);

    $featuredProducts = Product::featured()->get();

    expect($featuredProducts)->toHaveCount(2);
});