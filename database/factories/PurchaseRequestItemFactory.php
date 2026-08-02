<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PurchaseRequestItem>
 */
class PurchaseRequestItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 100),
            'unit' => fake()->randomElement(['pcs', 'kg', 'ltr', 'm', 'box']),
            'estimated_cost' => fake()->randomFloat(2, 1, 5000),
            'status_id' => Status::factory(),
            'notes' => null,
        ];
    }
}
