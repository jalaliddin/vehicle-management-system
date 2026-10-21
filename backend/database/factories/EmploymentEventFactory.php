<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmploymentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentEvent>
 */
class EmploymentEventFactory extends Factory
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
            'type' => 'hire',
            'effective_date' => now()->subYear()->toDateString(),
            'order_number' => fake()->numerify('##-K'),
            'order_date' => now()->subYear()->toDateString(),
            'organization_id' => fn (array $attributes) => Employee::find($attributes['employee_id'])?->organization_id,
        ];
    }
}
