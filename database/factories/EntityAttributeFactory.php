<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<\App\Models\EntityAttribute>
 */
class EntityAttributeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entity_type' => null,
            'entity_id' => null,
            'key' => fake()->word(),
            'value' => fake()->word(),
            'user_id' => null,
            'updated_by_id' => null,
        ];
    }

    public function forEntity(Model $entity): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => $entity::class,
            'entity_id' => $entity->id,
        ]);
    }
}
