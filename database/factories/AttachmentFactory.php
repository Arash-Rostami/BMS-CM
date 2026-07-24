<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Attachment>
 */
class AttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attachable_id' => null,
            'attachable_type' => null,
            'name' => fake()->word() . '.pdf',
            'path' => 'attachments/' . fake()->word() . '.pdf',
            'type' => fake()->randomElement(['application/pdf', 'image/png', 'image/jpeg', 'text/plain']),
            'status_id' => null,
            'user_id' => null,
            'updated_by_id' => null,
        ];
    }

    public function forAttachable(Model $attachable): static
    {
        return $this->state(fn (array $attributes) => [
            'attachable_type' => $attachable::class,
            'attachable_id' => $attachable->id,
        ]);
    }
}