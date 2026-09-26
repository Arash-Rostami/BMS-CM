<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\Company;
use App\Models\Currency;
use App\Models\RegisteredOrder;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_no' => fake()->unique()->bothify('PAY-#######'),
            'payment_date' => fake()->optional()->date(),
            'payment_deadline' => fake()->optional()->date(),
            'status_id' => Status::factory(),
            'payor_id' => Company::factory(),
            'payee_id' => Company::factory(),
            'currency_id' => Currency::factory(),
            'targetable_type' => RegisteredOrder::class,
            'targetable_id' => RegisteredOrder::factory(),
            'payable_amount' => fake()->randomFloat(2, 100, 1000000),
            'total_amount' => fake()->randomFloat(2, 100, 1000000),
            'exchange_rate' => fake()->randomFloat(5, 1, 500000),
            'bank_charges' => fake()->randomFloat(2, 0, 5000),
            'beneficiary_name' => fake()->optional()->name(),
            'beneficiary_address' => fake()->optional()->address(),
            'bank_id' => Bank::factory(),
            'bank_address' => fake()->optional()->address(),
            'account_no' => fake()->optional()->numerify('##########'),
            'swift' => fake()->optional()->bothify('??????'),
            'iban' => fake()->optional()->bothify('??##????????????????'),
            'notes' => fake()->optional()->sentence(),
            'user_id' => null,
            'updated_by_id' => null,
        ];
    }

    public function forTargetable(Model $model): static
    {
        return $this->state(fn (array $attributes) => [
            'targetable_type' => $model::class,
            'targetable_id' => $model->id,
        ]);
    }
}
