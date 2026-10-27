<?php

namespace Database\Factories;

use App\Models\FuelShift;
use App\Models\FuelStation;
use App\Models\User;
use App\Support\Fuel\Quantity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelShift>
 */
class FuelShiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fuel_station_id' => FuelStation::factory(),
            'status' => 'open',
            'opened_by' => User::factory(),
            'opened_at' => now()->subHours(2),
            'opening_balance' => fn (array $attributes) => FuelStation::find($attributes['fuel_station_id'])?->current_balance ?? 0,
            'opening_measured' => fn (array $attributes) => $attributes['opening_balance'],
        ];
    }

    public function closed(string $measured, ?string $note = null): static
    {
        return $this->state([
            'status' => 'closed',
            'closed_by' => fn (array $attributes) => $attributes['opened_by'],
            'closed_at' => now(),
            'closing_balance' => fn (array $attributes) => $attributes['opening_balance'],
            'closing_measured' => $measured,
            'discrepancy' => fn (array $attributes) => Quantity::format(Quantity::toHundredths($measured) - Quantity::toHundredths($attributes['closing_balance'])),
            'discrepancy_note' => $note,
        ]);
    }
}
