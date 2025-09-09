<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\Tag;

// Get all products and tags
$products = Product::all();
$tags = Tag::all();

if ($products->isEmpty() || $tags->isEmpty()) {
    echo "No products or tags found.\n";
    exit;
}

echo "Associating products with tags...\n";

// Associate products with relevant tags
foreach ($products as $product) {
    $productName = strtolower($product->name);
    $productDescription = strtolower($product->description ?? '');
    $associatedTags = [];
    
    // Electronics and tech products
    if (str_contains($productName, 'phone') || str_contains($productName, 'smartphone')) {
        $associatedTags[] = $tags->where('name', 'Electronics')->first()?->id;
        $associatedTags[] = $tags->where('name', 'Smartphones')->first()?->id;
    }
    
    if (str_contains($productName, 'laptop') || str_contains($productName, 'computer')) {
        $associatedTags[] = $tags->where('name', 'Electronics')->first()?->id;
        $associatedTags[] = $tags->where('name', 'Laptops')->first()?->id;
    }
    
    if (str_contains($productName, 'headphone') || str_contains($productName, 'speaker') || str_contains($productName, 'audio')) {
        $associatedTags[] = $tags->where('name', 'Electronics')->first()?->id;
        $associatedTags[] = $tags->where('name', 'Audio')->first()?->id;
    }
    
    if (str_contains($productName, 'watch') || str_contains($productName, 'fitness')) {
        $associatedTags[] = $tags->where('name', 'Wearables')->first()?->id;
    }
    
    if (str_contains($productName, 'game') || str_contains($productName, 'gaming')) {
        $associatedTags[] = $tags->where('name', 'Gaming')->first()?->id;
    }
    
    // Fashion and accessories
    if (str_contains($productName, 'shirt') || str_contains($productName, 'dress') || str_contains($productName, 'clothing')) {
        $associatedTags[] = $tags->where('name', 'Fashion')->first()?->id;
    }
    
    // Sports
    if (str_contains($productName, 'sport') || str_contains($productName, 'fitness') || str_contains($productName, 'exercise')) {
        $associatedTags[] = $tags->where('name', 'Sports')->first()?->id;
    }
    
    // Default to Electronics for tech-sounding products
    if (empty($associatedTags) && (str_contains($productName, 'pro') || str_contains($productName, 'tech') || str_contains($productName, 'digital'))) {
        $associatedTags[] = $tags->where('name', 'Electronics')->first()?->id;
    }
    
    // Add Accessories tag for accessory-like products
    if (str_contains($productName, 'case') || str_contains($productName, 'cover') || str_contains($productName, 'accessory')) {
        $associatedTags[] = $tags->where('name', 'Accessories')->first()?->id;
    }
    
    // Remove null values and attach tags
    $associatedTags = array_filter($associatedTags);
    
    if (!empty($associatedTags)) {
        $product->tags()->sync($associatedTags);
        echo "Associated product '{$product->name}' with " . count($associatedTags) . " tags.\n";
    } else {
        // Default to Electronics tag if no specific match
        $electronicsTag = $tags->where('name', 'Electronics')->first();
        if ($electronicsTag) {
            $product->tags()->sync([$electronicsTag->id]);
            echo "Associated product '{$product->name}' with default Electronics tag.\n";
        }
    }
}

echo "\nTag association completed!\n";
echo "Re-importing products to Meilisearch...\n";

// Re-import products to Meilisearch
Product::makeAllSearchable();

echo "Products re-imported to Meilisearch successfully!\n";