<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Status>
 */
class StatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['PurchaseRequest', 'ProformaInvoice', 'PurchaseOrder', 'Shipment']),
            'english_type' => fake()->optional()->randomElement(['PurchaseRequest', 'ProformaInvoice', 'PurchaseOrder', 'Shipment']),
            'name' => fake()->unique()->word(),
            'english_name' => fake()->optional()->word(),
            'user_id' => null,
            'updated_by_id' => null,
        ];
    }
}