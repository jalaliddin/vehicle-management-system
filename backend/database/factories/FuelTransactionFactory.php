<?php

namespace Database\Factories;

use App\Models\FuelStation;
use App\Models\FuelTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelTransaction>
 */
class FuelTransactionFactory extends Factory
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
            'kind' => FuelTransaction::KIND_REFUEL,
            'type' => 'out',
            'amount' => 40,
            'fuel_type' => fn (array $attributes) => FuelStation::find($attributes['fuel_station_id'])?->fuel_type ?? 'benzin',
        ];
    }

    public function receipt(string|float $amount = 100): static
    {
        return $this->state(['kind' => FuelTransaction::KIND_RECEIPT, 'type' => 'in', 'amount' => $amount, 'document_amount' => $amount]);
    }
}
