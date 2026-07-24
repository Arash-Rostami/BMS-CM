<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 200),
            'unit' => fake()->randomElement(['pcs', 'kg', 'ltr', 'm', 'box']),
            'unit_price' => fake()->randomFloat(2, 1, 10000),
            'net_weight' => fake()->optional()->randomFloat(2, 0.1, 1000),
            'gross_weight' => fake()->optional()->randomFloat(2, 0.1, 1200),
        ];
    }
}