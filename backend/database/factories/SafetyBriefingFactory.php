<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\SafetyBriefing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SafetyBriefing>
 */
class SafetyBriefingFactory extends Factory
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
            'type' => 'repeated',
            'conducted_on' => now()->subMonth()->toDateString(),
            'topic' => fake()->sentence(4),
            'instructor_name' => fake()->name(),
        ];
    }
}
