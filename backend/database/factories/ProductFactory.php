<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(3, true);
        
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->paragraphs(3, true),
            'short_description' => $this->faker->sentence(),
            'sku' => $this->faker->unique()->regexify('[A-Z]{3}[0-9]{6}'),
            'barcode' => $this->faker->ean13(),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'compare_price' => $this->faker->optional(0.3)->randomFloat(2, 15, 1200),
            'cost_price' => $this->faker->randomFloat(2, 5, 500),
            'track_inventory' => $this->faker->boolean(80),
            'inventory_quantity' => $this->faker->numberBetween(0, 100),
            'low_stock_threshold' => $this->faker->numberBetween(5, 20),
            'weight' => $this->faker->randomFloat(2, 0.1, 50),
            'weight_unit' => $this->faker->randomElement(['kg', 'g', 'lb', 'oz']),
            'dimensions' => [
                'length' => $this->faker->randomFloat(2, 1, 100),
                'width' => $this->faker->randomFloat(2, 1, 100),
                'height' => $this->faker->randomFloat(2, 1, 100),
                'unit' => $this->faker->randomElement(['cm', 'in', 'm'])
            ],
            'status' => $this->faker->randomElement(['active', 'inactive', 'draft']),
            'is_featured' => $this->faker->boolean(20),
            'is_digital' => $this->faker->boolean(10),
            'requires_shipping' => $this->faker->boolean(90),
            'meta_title' => $this->faker->optional(0.7)->sentence(),
            'meta_description' => $this->faker->optional(0.7)->sentence(),
            'meta_keywords' => $this->faker->optional(0.5)->words(5),
            'images' => [
                $this->faker->imageUrl(640, 480, 'products'),
                $this->faker->imageUrl(640, 480, 'products')
            ],
            'gallery' => [
                $this->faker->imageUrl(800, 600, 'products'),
                $this->faker->imageUrl(800, 600, 'products'),
                $this->faker->imageUrl(800, 600, 'products')
            ],
            'attributes' => [
                'color' => $this->faker->colorName(),
                'material' => $this->faker->randomElement(['cotton', 'polyester', 'wool', 'silk', 'leather'])
            ],
            'variants' => [],
            'sort_order' => $this->faker->numberBetween(1, 1000),
            'published_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 year', 'now')
        ];
    }

    /**
     * Indicate that the product is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Indicate that the product is digital.
     */
    public function digital(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_digital' => true,
            'requires_shipping' => false,
            'weight' => 0,
            'dimensions' => null
        ]);
    }

    /**
     * Indicate that the product is out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'inventory_quantity' => 0,
            'track_inventory' => true
        ]);
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}