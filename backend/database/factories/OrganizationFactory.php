<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->unique()->city();

        return [
            'name' => "{$city} gaz ta'minoti",
            'short_name' => $city,
            'code' => fake()->unique()->numerify('ORG-####'),
            'address' => fake()->address(),
            'phone' => fake()->numerify('+99862#######'),
            'director' => fake()->name(),
            'is_active' => true,
        ];
    }
}
