<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        $types = fake()->randomElements(
            array_keys(Company::getAvailableTypes()),
            fake()->numberBetween(1, 3),
        );

        return [
            'name' => fake()->unique()->company(),
            'english_name' => fake()->unique()->company(),
            'description' => fake()->optional()->paragraph(),
            'types' => $types,
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

    public function seller(): static
    {
        return $this->state(fn (array $attributes) => [
            'types' => [Company::TYPE_SELLER],
            'is_active' => true,
        ]);
    }

    public function buyer(): static
    {
        return $this->state(fn (array $attributes) => [
            'types' => [Company::TYPE_BUYER],
            'is_active' => true,
        ]);
    }
}
