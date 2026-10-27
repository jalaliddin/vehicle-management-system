<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PpeIssue;
use App\Models\PpeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PpeIssue>
 */
class PpeIssueFactory extends Factory
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
            'ppe_item_id' => PpeItem::factory(),
            'quantity' => 1,
            'issued_on' => now()->subMonths(6)->toDateString(),
            'due_on' => now()->addMonths(6)->toDateString(),
            'status' => 'in_use',
        ];
    }
}
