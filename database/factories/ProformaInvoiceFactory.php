<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Currency;
use App\Models\ProformaInvoice;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ProformaInvoice>
 */
class ProformaInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_no' => fake()->unique()->bothify('PI-######'),
            'invoice_date' => fake()->date(),
            'contract_no' => fake()->optional()->bothify('CT-######'),
            'buyer_comm_card_num' => null,
            'seller_id' => Company::factory(),
            'buyer_id' => Company::factory(),
            'validity_date' => fake()->dateTimeBetween('now', '+90 days'),
            'beneficiary_country' => fake()->countryCode(),
            'origin_country' => fake()->countryCode(),
            'destination_country' => fake()->countryCode(),
            'transport_mode' => fake()->randomElement(['air', 'sea', 'land', 'multimodal']),
            'port_of_discharge' => fake()->optional()->city(),
            'port_of_loading' => fake()->optional()->city(),
            'delivery_terms' => fake()->randomElement(['cfr', 'cif', 'cip', 'cpt', 'dap', 'ddp', 'dpu', 'exw', 'fas', 'fca', 'fob']),
            'main_currency_id' => Currency::factory(),
            'secondary_currency_id' => null,
            'discount' => fake()->optional()->randomFloat(2, 0, 1000),
            'freight_charges' => fake()->optional()->randomFloat(2, 0, 5000),
            'other_charges' => fake()->optional()->randomFloat(2, 0, 2000),
            'total_amount' => fake()->randomFloat(2, 100, 100000),
            'notes' => null,
            'user_id' => null,
            'updated_by_id' => null,
        ];
    }
}