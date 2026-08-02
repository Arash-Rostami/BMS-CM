<?php

namespace Database\Factories;

use App\Filament\Resources\Master\CompanyResource\Enums\Type;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        $types = fake()->randomElements(
            array_column(Type::cases(), 'value'),
            fake()->numberBetween(1, 3),
        );

        return [
            'name' => fake()->unique()->company(),
            'english_name' => fake()->unique()->company(),
            'description' => fake()->optional()->paragraph(),
            'types' => $types,
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
