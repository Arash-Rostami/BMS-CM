<?php

namespace Database\Factories;

use App\Models\Specification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<\App\Models\Specification>
 */
class SpecificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'specifiable_type' => null,
            'specifiable_id' => null,
            'hs_code' => fake()->optional()->numerify('####.##.####'),
            'import_duty' => fake()->optional()->numerify('##%'),
            'packing_type' => fake()->optional()->randomElement(['crate', 'pallet', 'carton', 'drum']),
            'vat_exempt' => fake()->boolean(),
            'tax_id' => fake()->optional()->numerify('TAX-########'),
            'manufacturer' => fake()->optional()->company(),
            'import_licenses' => ['LIC-' . fake()->numerify('####')],
            'extra' => [
                'certification' => 'ISO 9001',
                'origin' => fake()->country(),
            ],
            'user_id' => null,
            'updated_by_id' => null,
        ];
    }

    public function forSpecifiable(Model $specifiable): static
    {
        return $this->state(fn (array $attributes) => [
            'specifiable_type' => $specifiable::class,
            'specifiable_id' => $specifiable->id,
        ]);
    }
}