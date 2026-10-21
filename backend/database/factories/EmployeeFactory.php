<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'department_id' => null,
            'position_id' => null,
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'middle_name' => null,
            'pinfl' => fake()->unique()->numerify('##############'),
            'employment_type' => 'staff',
            'status' => 'active',
            'hire_date' => fake()->dateTimeBetween('-10 years', '-1 month')->format('Y-m-d'),
            'annual_leave_days' => 21,
        ];
    }

    public function dismissed(): static
    {
        return $this->state(fn () => ['status' => 'dismissed', 'dismissal_date' => now()->subWeek()->toDateString()]);
    }
}
