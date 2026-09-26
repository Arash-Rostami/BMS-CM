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
        $type = fake()->randomElement(['PurchaseRequest', 'ProformaInvoice', 'PurchaseOrder', 'Shipment']);
        $name = fake()->unique()->word();

        return [
            'type' => $type,
            'english_type' => $type,
            'name' => $name,
            'english_name' => $name,
            'user_id' => null,
            'updated_by_id' => null,
        ];
    }
}
