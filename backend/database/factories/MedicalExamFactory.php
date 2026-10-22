<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\MedicalExam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalExam>
 */
class MedicalExamFactory extends Factory
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
            'type' => 'periodic',
            'exam_date' => now()->subMonth()->toDateString(),
            'institution' => 'Tuman poliklinikasi',
            'result' => 'fit',
            'next_due_date' => now()->addMonths(11)->toDateString(),
        ];
    }
}
