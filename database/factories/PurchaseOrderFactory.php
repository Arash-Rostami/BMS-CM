<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'po_number' => fake()->unique()->bothify('PO-######'),
            'seller_id' => Company::factory(),
            'buyer_id' => Company::factory(),
            'status_id' => Status::factory(),
            'order_date' => fake()->date(),
            'validity_date' => fake()->dateTimeBetween('now', '+60 days'),
            'expected_delivery_date' => fake()->optional()->dateTimeBetween('now', '+120 days'),
            'incoterms' => fake()->randomElement(['cfr', 'cif', 'cip', 'cpt', 'dap', 'ddp', 'dpu', 'exw', 'fas', 'fca', 'fob']),
            'shipping_address' => fake()->optional()->address(),
            'packing_details' => null,
            'currency_id' => Currency::factory(),
            'notes' => null,
            'user_id' => User::factory(),
            'updated_by_id' => null,
        ];
    }
}
