<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeAbsence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeAbsence>
 */
class EmployeeAbsenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => 'annual_leave',
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date' => now()->subDays(7)->toDateString(),
            'is_planned' => false,
        ];
    }
}
