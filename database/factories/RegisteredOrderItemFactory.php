<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\RegisteredOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\RegisteredOrderItem>
 */
class RegisteredOrderItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 500);
        $unitPrice = fake()->randomFloat(2, 1, 10000);

        return [
            'registered_order_id' => RegisteredOrder::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit' => fake()->randomElement(['pcs', 'kg', 'ltr', 'm', 'box']),
            'unit_price' => $unitPrice,
            'net_weight' => fake()->optional()->randomFloat(2, 0.1, 1000),
            'gross_weight' => fake()->optional()->randomFloat(2, 0.1, 1200),
            'entrance_fee' => fake()->optional()->randomFloat(2, 0, 2000),
            'shipping_cost' => fake()->optional()->randomFloat(2, 0, 5000),
            'extra_cost' => fake()->optional()->randomFloat(2, 0, 1500),
            'line_total' => round($quantity * $unitPrice, 2),
            'packing_details' => null,
            'description' => null,
        ];
    }
}
