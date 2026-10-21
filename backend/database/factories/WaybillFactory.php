<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Organization;
use App\Models\Vehicle;
use App\Models\Waybill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Waybill>
 */
class WaybillFactory extends Factory
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
            'vehicle_id' => fn (array $attributes) => Vehicle::factory()->state(['organization_id' => $attributes['organization_id']]),
            'driver_id' => fn (array $attributes) => Driver::factory()->state(['organization_id' => $attributes['organization_id']]),
            'vehicle_type' => 'car',
            'created_date' => today(),
            'valid_until' => today()->addDay(),
            'trip_count' => 1,
            'destination' => fake()->city(),
            'status' => 'draft',
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
