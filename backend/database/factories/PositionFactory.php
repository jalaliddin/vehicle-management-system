<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'medical_exam_months' => null,
            'briefing_months' => null,
            'is_active' => true,
        ];
    }

    /**
     * A position whose holders need periodic medical exams and repeated briefings.
     */
    public function withPeriodicities(int $medicalExamMonths = 12, int $briefingMonths = 6): static
    {
        return $this->state(fn () => [
            'medical_exam_months' => $medicalExamMonths,
            'briefing_months' => $briefingMonths,
        ]);
    }
}
