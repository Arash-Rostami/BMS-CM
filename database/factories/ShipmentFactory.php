<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\RegisteredOrder;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Shipment>
 */
class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'registered_order_id' => RegisteredOrder::factory(),
            'shipment_no' => fake()->unique()->bothify('SHP-#######'),
            'company_id' => Company::factory(),
            'part' => fake()->optional()->numberBetween(1, 50),
            'contract_no' => fake()->optional()->bothify('CON-######'),
            'warehouse_date' => fake()->optional()->date(),
            'exit_date' => fake()->optional()->date(),
            'remittance_amount' => fake()->randomFloat(2, 0, 1000000),
            'customs_quantity' => fake()->randomFloat(2, 0, 100000),
            'shipped_quantity' => fake()->randomFloat(2, 0, 100000),
            'bl_number' => fake()->optional()->bothify('BLU-######'),
            'booking_no' => fake()->optional()->bothify('BKG-######'),
            'eta' => fake()->optional()->date(),
            'etd' => fake()->optional()->date(),
            'container_no' => fake()->optional()->numberBetween(1, 30),
            'container_type' => fake()->optional()->randomElement([
                '20ft Standard', '40ft Standard', '40ft High Cube',
                '20ft Refrigerated', '40ft Refrigerated',
                '20ft Open Top', '40ft Open Top',
                '20ft Flat Rack', '40ft Flat Rack',
                'LCL', 'Bulk',
            ]),
            'container_status_id' => Status::factory(),
            'operation_status_id' => Status::factory(),
            'shipment_status_id' => Status::factory(),
            'status_id' => Status::factory(),
            'doc_status_id' => Status::factory(),
            'docs' => [],
            'notes' => fake()->optional()->sentence(),
            'user_id' => null,
            'updated_by_id' => null,
        ];
    }
}
