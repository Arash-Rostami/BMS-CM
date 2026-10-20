<?php

namespace Database\Factories;

use App\Models\CalendarHit;
use App\Models\CalendarRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarRead>
 */
class CalendarReadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'calendar_hit_id' => CalendarHit::factory(),
            'event_date' => today()->toDateString(),
            'read_at' => now(),
        ];
    }
}
