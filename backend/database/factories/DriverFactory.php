<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Employee;
use App\Models\Organization;
use App\Support\Hr\DriverEmployeeBackfill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
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
            'full_name' => fake()->name(),
            'license_number' => fake()->unique()->bothify('AA#######'),
            'license_category' => 'B',
            'work_experience' => fake()->numberBetween(0, 30),
            'pinfl' => fake()->unique()->numerify('##############'),
            'status' => 'active',
            'employee_id' => fn (array $attributes) => Employee::factory()->create(array_merge(
                DriverEmployeeBackfill::splitName($attributes['full_name']),
                ['organization_id' => $attributes['organization_id'], 'pinfl' => $attributes['pinfl']],
            ))->id,
        ];
    }
}
