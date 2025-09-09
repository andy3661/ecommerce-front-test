<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            ['name' => 'Electronics', 'description' => 'Electronic devices and gadgets', 'color' => '#007bff'],
            ['name' => 'Smartphones', 'description' => 'Mobile phones and accessories', 'color' => '#28a745'],
            ['name' => 'Laptops', 'description' => 'Portable computers and notebooks', 'color' => '#17a2b8'],
            ['name' => 'Gaming', 'description' => 'Gaming devices and accessories', 'color' => '#ffc107'],
            ['name' => 'Audio', 'description' => 'Headphones, speakers, and audio equipment', 'color' => '#dc3545'],
            ['name' => 'Wearables', 'description' => 'Smartwatches and fitness trackers', 'color' => '#6f42c1'],
            ['name' => 'Accessories', 'description' => 'Device accessories and peripherals', 'color' => '#fd7e14'],
            ['name' => 'Home & Garden', 'description' => 'Home improvement and garden items', 'color' => '#20c997'],
            ['name' => 'Fashion', 'description' => 'Clothing and fashion accessories', 'color' => '#e83e8c'],
            ['name' => 'Sports', 'description' => 'Sports and fitness equipment', 'color' => '#6c757d'],
            ['name' => 'Books', 'description' => 'Books and educational materials', 'color' => '#343a40'],
            ['name' => 'Toys', 'description' => 'Toys and games for children', 'color' => '#f8f9fa'],
            ['name' => 'Health', 'description' => 'Health and wellness products', 'color' => '#198754'],
            ['name' => 'Beauty', 'description' => 'Beauty and personal care products', 'color' => '#d63384'],
            ['name' => 'Automotive', 'description' => 'Car accessories and automotive products', 'color' => '#495057']
        ];

        foreach ($tags as $index => $tagData) {
            Tag::create([
                'name' => $tagData['name'],
                'slug' => Str::slug($tagData['name']),
                'description' => $tagData['description'],
                'color' => $tagData['color'],
                'status' => 'active',
                'sort_order' => $index + 1
            ]);
        }
    }
}
