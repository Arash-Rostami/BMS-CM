<?php

namespace Database\Factories;

use App\Models\Custom;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Custom>
 */
class CustomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'custom_no' => fake()->unique()->bothify('CST-#######'),
            'shipment_id' => Shipment::factory(),
            'registered_order_id' => RegisteredOrder::factory(),
            'shipment_no' => fake()->optional()->bothify('SHP-#######'),
            'contract_no' => fake()->optional()->bothify('CON-######'),
            'declaration_no' => fake()->optional()->bothify('DEC-######'),
            'clearance_type' => fake()->optional()->randomElement(['definitive', 'percentage']),
            'commitment_balance' => fake()->randomFloat(2, 0, 1000000),
            'clearance_date' => fake()->optional()->date(),
            'doc_submission_date' => fake()->optional()->date(),
            'ten_percent_exit_date' => fake()->optional()->date(),
            'rial_return_date' => fake()->optional()->date(),
            'clearance_status_id' => Status::factory(),
            'bank_guarantee_status_id' => Status::factory(),
            'commitment_status_id' => Status::factory(),
            'notes' => fake()->optional()->sentence(),
            'user_id' => null,
            'updated_by_id' => null,
        ];
    }
}