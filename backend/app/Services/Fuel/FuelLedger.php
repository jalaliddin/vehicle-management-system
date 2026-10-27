<?php

namespace App\Services\Fuel;

use App\Models\FuelShift;
use App\Models\FuelStation;
use App\Models\User;
use App\Support\Fuel\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every write that changes a station's fuel balance, each under a lock on the station row.
 */
class FuelLedger
{
    public function openShift(FuelStation $station, string|int|float $measured, User $by): FuelShift
    {
        return DB::transaction(function () use ($station, $measured, $by) {
            $station = $this->lock($station);

            if ($station->openShift()->exists()) {
                throw ValidationException::withMessages(['shift' => 'Bu shahobchada ochiq smena bor']);
            }

            return $station->shifts()->create([
                'status' => 'open',
                'opened_by' => $by->id,
                'opened_at' => now(),
                'opening_balance' => $station->current_balance,
                'opening_measured' => Quantity::format(Quantity::toHundredths($measured)),
            ]);
        });
    }

    public function closeShift(FuelShift $shift, string|int|float $measured, ?string $note, User $by): FuelShift
    {
        return DB::transaction(function () use ($shift, $measured, $note, $by) {
            $station = $this->lock($shift->station);
            $shift->refresh();

            if (! $shift->isOpen()) {
                throw ValidationException::withMessages(['shift' => 'Smena yopilgan']);
            }

            $book = Quantity::toHundredths($station->current_balance);
            $actual = Quantity::toHundredths($measured);

            if ($actual !== $book && blank($note)) {
                throw ValidationException::withMessages(['discrepancy_note' => 'Farq izohini yozing']);
            }

            $shift->update([
                'status' => 'closed',
                'closed_by' => $by->id,
                'closed_at' => now(),
                'closing_balance' => Quantity::format($book),
                'closing_measured' => Quantity::format($actual),
                'discrepancy' => Quantity::format($actual - $book),
                'discrepancy_note' => $note,
            ]);

            return $shift;
        });
    }

    private function lock(FuelStation $station): FuelStation
    {
        return FuelStation::lockForUpdate()->findOrFail($station->id);
    }
}
