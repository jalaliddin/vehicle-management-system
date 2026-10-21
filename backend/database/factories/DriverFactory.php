<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Organization;
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
        ];
    }
}
