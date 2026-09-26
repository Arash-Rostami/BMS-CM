<?php

namespace Database\Factories;

use App\Models\StatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<\App\Models\StatusHistory>
 */
class StatusHistoryFactory extends Factory
{
    protected $model = StatusHistory::class;

    public function definition(): array
    {
        return [
            'statusable_type' => null,
            'statusable_id' => null,
            'field' => 'status_id',
            'from_status_id' => null,
            'to_status_id' => null,
            'user_id' => null,
        ];
    }

    public function forStatusable(Model $statusable): static
    {
        return $this->state(fn (array $attributes) => [
            'statusable_type' => $statusable::class,
            'statusable_id' => $statusable->id,
        ]);
    }
}
