<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Currency>
 */
class CurrencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'ارز '.fake()->unique()->numberBetween(100000, 999999),
            'english_name' => fake()->unique()->currencyCode(),
            'description' => fake()->optional()->paragraph(),
            'is_active' => true,
            'user_id' => null,
            'updated_by_id' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
