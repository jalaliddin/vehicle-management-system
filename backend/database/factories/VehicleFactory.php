<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
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
            'state_number' => fake()->unique()->bothify('90#??###'),
            'model' => fake()->randomElement(['Chevrolet Damas', 'Chevrolet Cobalt', 'Isuzu NQR']),
            'manufacture_year' => fake()->numberBetween(2010, (int) date('Y')),
            'vehicle_type' => 'car',
            'fuel_type' => 'benzin',
            'status' => 'active',
        ];
    }
}
