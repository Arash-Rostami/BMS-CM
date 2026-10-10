<?php

namespace Database\Factories;

use App\Models\CalendarHit;
use App\Models\CalendarRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<CalendarHit>
 */
class CalendarHitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'calendar_rule_id' => CalendarRule::factory(),
            'subject_type' => 'App\\Models\\PurchaseRequest',
            'subject_id' => fake()->randomNumber(5),
            'label' => fake()->sentence(2),
            'event_date' => fake()->date(),
            'alerts_sent' => [],
            'overdue_count' => 0,
            'synced_at' => now(),
        ];
    }

    public function forSubject(Model $record): static
    {
        return $this->state(fn (): array => [
            'subject_type' => $record->getMorphClass(),
            'subject_id' => $record->getKey(),
        ]);
    }
}
