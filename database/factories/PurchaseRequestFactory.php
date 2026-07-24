<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pr_number' => fake()->unique()->bothify('PR-######'),
            'requester_id' => User::factory(),
            'department_id' => Department::factory(),
            'cost_center_id' => null,
            'required_by_date' => fake()->dateTimeBetween('now', '+30 days'),
            'total_estimated_cost' => fake()->randomFloat(2, 100, 100000),
            'urgency_level' => fake()->randomElement(['low', 'medium', 'high']),
            'status_id' => Status::factory(),
            'approver_id' => null,
            'approval_date' => null,
            'rejection_reason' => null,
            'notes' => null,
            'user_id' => null,
            'updated_by_id' => null,
        ];
    }
}