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
            'name' => fake()->unique()->currencyCode(),
            'english_name' => fake()->unique()->currency(),
            'description' => fake()->optional()->paragraph(),
            'is_active' => fake()->boolean(90),
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
