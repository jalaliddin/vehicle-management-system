<?php

namespace Database\Factories;

use App\Models\FuelSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelSupplier>
 */
class FuelSupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'tin' => fake()->numerify('#########'),
            'phone' => null,
            'is_active' => true,
        ];
    }
}
