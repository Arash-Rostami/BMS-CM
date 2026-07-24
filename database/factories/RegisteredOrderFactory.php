<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Currency;
use App\Models\RegisteredOrder;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\RegisteredOrder>
 */
class RegisteredOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ro_number' => fake()->unique()->bothify('RO-######'),
            'contract_no' => fake()->unique()->bothify('CT-######'),
            'official_registration_no' => fake()->unique()->bothify('REG-######'),
            'seller_id' => Company::factory(),
            'buyer_id' => Company::factory(),
            'status_id' => Status::factory(),
            'order_date' => fake()->date(),
            'validity_date' => fake()->optional()->dateTimeBetween('now', '+90 days'),
            'expected_delivery_date' => fake()->optional()->dateTimeBetween('now', '+180 days'),
            'incoterms' => fake()->randomElement(['cfr', 'cif', 'cip', 'cpt', 'dap', 'ddp', 'dpu', 'exw', 'fas', 'fca', 'fob']),
            'currency_id' => Currency::factory(),
            'currency_type' => fake()->randomElement(['export', 'exchange_center_1', 'exchange_center_2', 'individuals', 'other']),
            'insurance_number' => fake()->optional()->bothify('INS-######'),
            'insurance_provider' => fake()->optional()->company(),
            'insurance_date' => fake()->optional()->date(),
            'notes' => null,
            'user_id' => User::factory(),
            'updated_by_id' => null,
        ];
    }
}