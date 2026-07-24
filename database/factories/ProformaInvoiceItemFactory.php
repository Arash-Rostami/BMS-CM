<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProformaInvoice;
use App\Models\ProformaInvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ProformaInvoiceItem>
 */
class ProformaInvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'proforma_invoice_id' => ProformaInvoice::factory(),
            'product_id' => Product::factory(),
            'description' => fake()->optional()->sentence(),
            'origin' => fake()->optional()->countryCode(),
            'hs_code' => fake()->optional()->bothify('####.##'),
            'quantity' => fake()->numberBetween(1, 500),
            'net_weight' => fake()->optional()->randomFloat(3, 0.1, 500),
            'gross_weight' => fake()->optional()->randomFloat(3, 0.1, 600),
            'unit' => fake()->randomElement(['pcs', 'kg', 'ltr', 'm', 'box']),
            'unit_price' => fake()->randomFloat(2, 1, 10000),
            'freight_charges' => fake()->optional()->randomFloat(2, 0, 3000),
            'total_amount' => fake()->randomFloat(2, 10, 100000),
        ];
    }
}