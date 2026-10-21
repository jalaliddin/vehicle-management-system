<?php

namespace Database\Factories;

use App\Models\PpeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PpeItem>
 */
class PpeItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'kind' => fake()->randomElement(['clothing', 'footwear', 'ppe']),
            'unit' => 'dona',
            'default_wear_months' => null,
            'is_active' => true,
        ];
    }
}
