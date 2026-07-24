<?php

namespace Database\Factories;

use App\Models\BankProfile;
use App\Models\Company;
use App\Models\Currency;
use App\Models\RegisteredOrder;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\BankProfile>
 */
class BankProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bp_number' => fake()->unique()->bothify('BP-#######'),
            'status_id' => Status::factory(),
            'registered_order_id' => RegisteredOrder::factory(),
            'company_id' => Company::factory(),
            'bank_id' => null,
            'order_number' => fake()->numerify('ORD-######'),
            'supply_source' => fake()->word(),
            'requested_amount' => fake()->randomFloat(2, 1000, 1000000),
            'requested_currency_id' => Currency::factory(),
            'purchased_equivalent' => fake()->randomFloat(2, 1000, 1000000),
            'purchased_currency_id' => Currency::factory(),
            'commission_rate' => fake()->randomFloat(5, 0, 0.05),
            'commission_amount_purchased' => fake()->randomFloat(2, 0, 50000),
            'exchange_rate' => fake()->randomFloat(5, 100, 500000),
            'final_rate' => fake()->randomFloat(5, 100, 500000),
            'conversion_rate' => fake()->randomFloat(5, 0, 100),
            'documents_amount' => fake()->randomFloat(2, 0, 50000),
            'creation_date' => fake()->date(),
            'allocation_date' => fake()->optional()->date(),
            'purchase_date' => fake()->optional()->date(),
            'delivery_date' => fake()->optional()->date(),
            'payment_due_date' => fake()->optional()->date(),
            'commitment_payment_date' => fake()->optional()->date(),
            'notes' => fake()->optional()->sentence(),
            'user_id' => null,
            'updated_by_id' => null,
        ];
    }
}