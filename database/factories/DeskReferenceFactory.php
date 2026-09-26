<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\DeskReference>
 */
class DeskReferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'group_key' => fake()->slug(2),
            'version' => fake()->numberBetween(1, 5),
            'acknowledged_at' => now(),
        ];
    }

    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes) => [
            'acknowledged_at' => now(),
        ]);
    }
}
