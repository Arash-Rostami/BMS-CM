<?php

namespace Database\Factories;

use App\Models\Correspondence;
use App\Models\RegisteredOrder;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<\App\Models\Correspondence>
 */
class CorrespondenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'correspondable_type' => RegisteredOrder::class,
            'correspondable_id' => RegisteredOrder::factory(),
            'parent_id' => null,
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'type' => fake()->randomElement(['note', 'report', 'inquiry', 'warning']),
            'priority' => fake()->randomElement(['low', 'normal', 'high', 'urgent']),
            'is_internal' => fake()->boolean(),
            'is_private' => fake()->boolean(),
            'status_id' => Status::factory(),
            'user_id' => null,
            'updated_by_id' => null,
        ];
    }

    public function forCorrespondable(Model $model): static
    {
        return $this->state(fn (array $attributes) => [
            'correspondable_type' => $model::class,
            'correspondable_id' => $model->id,
        ]);
    }
}