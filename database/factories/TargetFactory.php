<?php

namespace Database\Factories;

use App\Models\Target;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<\App\Models\Target>
 */
class TargetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'targetable_type' => null,
            'targetable_id' => null,
            'year' => fake()->numberBetween(2020, 2030),
            'start_from' => fake()->date(),
            'end_in' => fake()->date(),
            'quantity' => fake()->optional()->randomFloat(2, 1, 1000),
            'amount' => fake()->optional()->randomFloat(2, 100, 100000),
            'achieved_quantity' => fake()->optional()->randomFloat(2, 0, 500),
            'achieved_amount' => fake()->optional()->randomFloat(2, 0, 50000),
            'metrics' => fake()->optional()->randomElement(['kg', 'tons', 'units', 'meters', 'liters']),
            'description' => fake()->optional()->sentence(),
            'tags' => fake()->words(3),
            'status' => fake()->randomElement(['active', 'inactive', 'achieved']),
            'user_id' => null,
            'updated_by_id' => null,
        ];
    }

    public function forTargetable(Model $targetable): static
    {
        return $this->state(fn (array $attributes) => [
            'targetable_type' => $targetable::class,
            'targetable_id' => $targetable->id,
        ]);
    }
}