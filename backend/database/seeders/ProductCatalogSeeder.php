<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create main categories
        $electronics = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'description' => 'Electronic devices and gadgets',
            'is_featured' => true,
            'sort_order' => 1
        ]);

        $clothing = Category::create([
            'name' => 'Clothing',
            'slug' => 'clothing',
            'description' => 'Fashion and apparel',
            'is_featured' => true,
            'sort_order' => 2
        ]);

        $home = Category::create([
            'name' => 'Home & Garden',
            'slug' => 'home-garden',
            'description' => 'Home improvement and garden supplies',
            'is_featured' => true,
            'sort_order' => 3
        ]);

        $books = Category::create([
            'name' => 'Books',
            'slug' => 'books',
            'description' => 'Books and literature',
            'is_featured' => false,
            'sort_order' => 4
        ]);

        // Create subcategories for Electronics
        $smartphones = Category::create([
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'description' => 'Mobile phones and accessories',
            'parent_id' => $electronics->id,
            'sort_order' => 1
        ]);

        $laptops = Category::create([
            'name' => 'Laptops',
            'slug' => 'laptops',
            'description' => 'Portable computers',
            'parent_id' => $electronics->id,
            'sort_order' => 2
        ]);

        $headphones = Category::create([
            'name' => 'Headphones',
            'slug' => 'headphones',
            'description' => 'Audio equipment',
            'parent_id' => $electronics->id,
            'sort_order' => 3
        ]);

        // Create subcategories for Clothing
        $mensClothing = Category::create([
            'name' => "Men's Clothing",
            'slug' => 'mens-clothing',
            'description' => 'Clothing for men',
            'parent_id' => $clothing->id,
            'sort_order' => 1
        ]);

        $womensClothing = Category::create([
            'name' => "Women's Clothing",
            'slug' => 'womens-clothing',
            'description' => 'Clothing for women',
            'parent_id' => $clothing->id,
            'sort_order' => 2
        ]);

        // Create sample products
        $products = [
            [
                'name' => 'iPhone 15 Pro',
                'slug' => 'iphone-15-pro',
                'description' => 'Latest iPhone with advanced camera system and A17 Pro chip',
                'short_description' => 'Premium smartphone with cutting-edge technology',
                'sku' => 'IPH15PRO001',
                'price' => 999.00,
                'compare_price' => 1099.00,
                'inventory_quantity' => 50,
                'is_featured' => true,
                'images' => ['iphone-15-pro-1.jpg', 'iphone-15-pro-2.jpg'],
                'attributes' => [
                    'brand' => 'Apple',
                    'color' => 'Natural Titanium',
                    'storage' => '128GB'
                ],
                'published_at' => now(),
                'category_id' => $smartphones->id
            ],
            [
                'name' => 'Samsung Galaxy S24 Ultra',
                'slug' => 'samsung-galaxy-s24-ultra',
                'description' => 'Premium Android smartphone with S Pen and advanced AI features',
                'short_description' => 'Flagship Android phone with S Pen',
                'sku' => 'SAM24ULT001',
                'price' => 1199.00,
                'compare_price' => 1299.00,
                'inventory_quantity' => 30,
                'is_featured' => true,
                'images' => ['galaxy-s24-ultra-1.jpg', 'galaxy-s24-ultra-2.jpg'],
                'attributes' => [
                    'brand' => 'Samsung',
                    'color' => 'Titanium Black',
                    'storage' => '256GB'
                ],
                'published_at' => now(),
                'category_id' => $smartphones->id
            ],
            [
                'name' => 'MacBook Pro 14-inch',
                'slug' => 'macbook-pro-14-inch',
                'description' => 'Professional laptop with M3 chip, perfect for creative work',
                'short_description' => 'High-performance laptop for professionals',
                'sku' => 'MBP14M3001',
                'price' => 1999.00,
                'compare_price' => 2199.00,
                'inventory_quantity' => 25,
                'is_featured' => true,
                'images' => ['macbook-pro-14-1.jpg', 'macbook-pro-14-2.jpg'],
                'attributes' => [
                    'brand' => 'Apple',
                    'processor' => 'M3',
                    'ram' => '16GB',
                    'storage' => '512GB SSD'
                ],
                'published_at' => now(),
                'category_id' => $laptops->id
            ],
            [
                'name' => 'Sony WH-1000XM5',
                'slug' => 'sony-wh-1000xm5',
                'description' => 'Industry-leading noise canceling wireless headphones',
                'short_description' => 'Premium noise-canceling headphones',
                'sku' => 'SONY1000XM5',
                'price' => 399.00,
                'compare_price' => 449.00,
                'inventory_quantity' => 75,
                'is_featured' => true,
                'images' => ['sony-wh1000xm5-1.jpg', 'sony-wh1000xm5-2.jpg'],
                'attributes' => [
                    'brand' => 'Sony',
                    'type' => 'Over-ear',
                    'connectivity' => 'Wireless',
                    'noise_canceling' => 'Yes'
                ],
                'published_at' => now(),
                'category_id' => $headphones->id
            ],
            [
                'name' => 'Classic Cotton T-Shirt',
                'slug' => 'classic-cotton-t-shirt',
                'description' => 'Comfortable 100% cotton t-shirt in various colors',
                'short_description' => 'Essential cotton t-shirt',
                'sku' => 'TSHIRT001',
                'price' => 24.99,
                'compare_price' => 29.99,
                'inventory_quantity' => 200,
                'is_featured' => false,
                'images' => ['cotton-tshirt-1.jpg', 'cotton-tshirt-2.jpg'],
                'attributes' => [
                    'material' => '100% Cotton',
                    'fit' => 'Regular',
                    'care' => 'Machine washable'
                ],
                'variants' => [
                    'sizes' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
                    'colors' => ['White', 'Black', 'Navy', 'Gray', 'Red']
                ],
                'published_at' => now(),
                'category_id' => $mensClothing->id
            ],
            [
                'name' => 'Elegant Summer Dress',
                'slug' => 'elegant-summer-dress',
                'description' => 'Lightweight and breathable summer dress perfect for any occasion',
                'short_description' => 'Stylish summer dress',
                'sku' => 'DRESS001',
                'price' => 79.99,
                'compare_price' => 99.99,
                'inventory_quantity' => 60,
                'is_featured' => true,
                'images' => ['summer-dress-1.jpg', 'summer-dress-2.jpg'],
                'attributes' => [
                    'material' => 'Cotton blend',
                    'season' => 'Summer',
                    'occasion' => 'Casual/Formal'
                ],
                'variants' => [
                    'sizes' => ['XS', 'S', 'M', 'L', 'XL'],
                    'colors' => ['Floral Blue', 'Solid Black', 'Floral Pink']
                ],
                'published_at' => now(),
                'category_id' => $womensClothing->id
            ]
        ];

        foreach ($products as $productData) {
            $categoryId = $productData['category_id'];
            unset($productData['category_id']);
            
            $product = Product::create($productData);
            
            // Attach to category
            $product->categories()->attach($categoryId, ['is_primary' => true]);
        }

        $this->command->info('Product catalog seeded successfully!');
    }
}