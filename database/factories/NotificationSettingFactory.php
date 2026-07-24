<?php

namespace Database\Factories;

use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\NotificationSetting>
 */
class NotificationSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'settings' => [
                'is_active' => fake()->boolean(),
                'actions' => fake()->randomElements(['create', 'update', 'delete'], fake()->numberBetween(1, 3)),
                'columns' => fake()->randomElements(['name', 'status_id', 'user_id', 'created_at'], fake()->numberBetween(1, 4)),
                'tables' => [fake()->word()],
                'users' => [fake()->numberBetween(1, 100)],
                'values' => [fake()->word() => fake()->word()],
            ],
            'notification_type' => fake()->randomElement(['in_app', 'email', 'sms']),
            'notes' => fake()->optional()->sentence(),
            'user_id' => User::factory(),
            'updated_by_id' => null,
        ];
    }
}