<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderStatusHistory>
 */
class OrderStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
        
        return [
            'order_id' => Order::factory(),
            'status' => $this->faker->randomElement($statuses),
            'notes' => $this->faker->optional(0.7)->sentence(),
            'changed_by' => User::factory(),
            'changed_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Indicate that the status change has notes.
     */
    public function withNotes(): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $this->faker->paragraph(),
        ]);
    }

    /**
     * Indicate that the status change was made by a specific user.
     */
    public function byUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'changed_by' => $user->id,
        ]);
    }

    /**
     * Indicate a specific status.
     */
    public function status(string $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}