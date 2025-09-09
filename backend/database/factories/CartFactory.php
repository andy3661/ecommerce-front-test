<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cart>
 */
class CartFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Cart::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => $this->faker->optional(0.3)->uuid(),
            'product_id' => Product::factory(),
            'product_variant' => $this->faker->optional(0.4)->randomElements([
                'size' => $this->faker->randomElement(['S', 'M', 'L', 'XL']),
                'color' => $this->faker->colorName()
            ]),
            'quantity' => $this->faker->numberBetween(1, 5),
            'unit_price' => $this->faker->randomFloat(2, 10, 500)
        ];
    }

    /**
     * Indicate that the cart item is for a guest user.
     */
    public function forGuest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'session_id' => Str::uuid()->toString()
        ]);
    }

    /**
     * Indicate that the cart item is for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'session_id' => null
        ]);
    }

    /**
     * Indicate that the cart item is for a specific product.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'unit_price' => $product->price
        ]);
    }

    /**
     * Indicate that the cart item has a specific quantity.
     */
    public function withQuantity(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $quantity
        ]);
    }

    /**
     * Indicate that the cart item has product variants.
     */
    public function withVariants(array $variants): static
    {
        return $this->state(fn (array $attributes) => [
            'product_variant' => $variants
        ]);
    }
}