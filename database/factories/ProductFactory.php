<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => $name,
            'english_name' => fake()->words(2, true),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->paragraph(),
            'code' => fake()->unique()->bothify('PRD-####-????'),
            'in_stock' => fake()->boolean(),
            'is_active' => true,
            'user_id' => null,
            'updated_by_id' => null,
            'category_id' => Category::factory(),
            'attributes' => [
                'color' => fake()->safeColorName(),
                'weight' => fake()->randomFloat(2, 0.1, 50),
            ],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
