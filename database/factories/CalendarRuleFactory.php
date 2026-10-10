<?php

namespace Database\Factories;

use App\Filament\Resources\Master\CalendarRuleResource\Enums\CalendarColor;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\Visibility;
use App\Models\CalendarRule;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarRule>
 */
class CalendarRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'subject' => PurchaseRequest::class,
            'filters' => ['rules' => []],
            'date_path' => 'required_by_date',
            'day_shift' => 0,
            'lead_times' => [7, 3, 1],
            'on_day' => true,
            'notification_type' => CalendarRule::NOTIFICATION_CHANNEL_IN_APP,
            'type' => RuleType::HEADS_UP->value,
            'color' => CalendarColor::SKY->value,
            'visibility' => Visibility::ME->value,
            'shared_role_ids' => null,
            'notify_emails' => null,
            'is_active' => true,
            'related_models' => [],
            'fingerprint' => sha1(uniqid()),
            'conditions_hash' => sha1(uniqid()),
            'user_id' => User::factory(),
            'updated_by_id' => null,
        ];
    }
}
