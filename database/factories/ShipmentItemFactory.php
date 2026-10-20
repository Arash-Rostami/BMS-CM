<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ShipmentItem>
 */
class ShipmentItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 500);
        $unitPrice = fake()->randomFloat(2, 1, 1000);
        $net = fake()->randomFloat(3, 1, 500);

        return [
            'shipment_id' => Shipment::factory(),
            'product_id' => Product::factory(),
            'name' => fake()->optional()->sentence(3),
            'english_name' => fake()->sentence(3),
            'hs_code' => fake()->optional()->bothify('########'),
            'origin' => fake()->optional()->countryCode(),
            'unit' => 'kg',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => round($quantity * $unitPrice, 5),
            'net_weight' => $net,
            'gross_weight' => $net + fake()->randomFloat(3, 0, 50),
        ];
    }
}
