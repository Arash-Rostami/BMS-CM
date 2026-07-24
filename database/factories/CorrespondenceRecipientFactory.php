<?php

namespace Database\Factories;

use App\Models\Correspondence;
use App\Models\CorrespondenceRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CorrespondenceRecipient>
 */
class CorrespondenceRecipientFactory extends Factory
{
    protected $model = CorrespondenceRecipient::class;

    public function definition(): array
    {
        return [
            'correspondence_id' => Correspondence::factory(),
            'user_id' => User::factory(),
            'type' => 'to',
            'read_at' => null,
        ];
    }

    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }
}