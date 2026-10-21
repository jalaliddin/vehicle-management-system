<?php

namespace Database\Factories;

use App\Models\FuelStation;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelStation>
 */
class FuelStationFactory extends Factory
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
            'name' => fake()->company().' AYoQSh',
            'fuel_type' => 'benzin',
            'capacity' => 10000,
            'current_balance' => 5000,
            'min_threshold' => 500,
        ];
    }
}
