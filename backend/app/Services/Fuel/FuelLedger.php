<?php

namespace App\Services\Fuel;

use App\Models\FuelShift;
use App\Models\FuelStation;
use App\Models\FuelTransaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Waybill;
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

    /**
     * @param  array{fuel_supplier_id: int, document_number: string, document_date: string, document_amount: string|int|float, amount: string|int|float, price_per_liter?: string|int|float|null, notes?: string|null}  $data
     */
    public function receive(FuelStation $station, array $data, User $by): FuelTransaction
    {
        return DB::transaction(function () use ($station, $data, $by) {
            $station = $this->lock($station);
            $shift = $this->openShiftOf($station);
            $amount = Quantity::toHundredths($data['amount']);
            $balance = Quantity::toHundredths($station->current_balance);

            if ($balance + $amount > Quantity::toHundredths($station->capacity)) {
                throw ValidationException::withMessages(['amount' => "Sig'imdan oshib ketadi"]);
            }

            $transaction = $station->transactions()->create([
                'kind' => FuelTransaction::KIND_RECEIPT,
                'type' => 'in',
                'fuel_shift_id' => $shift->id,
                'fuel_supplier_id' => $data['fuel_supplier_id'],
                'document_number' => $data['document_number'],
                'document_date' => $data['document_date'],
                'document_amount' => Quantity::format(Quantity::toHundredths($data['document_amount'])),
                'amount' => Quantity::format($amount),
                'fuel_type' => $station->fuel_type,
                'price_per_liter' => $data['price_per_liter'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $by->id,
            ]);

            $station->update(['current_balance' => Quantity::format($balance + $amount)]);

            return $transaction;
        });
    }

    /**
     * @param  array{vehicle_id: int, odometer: int, amount: string|int|float, driver_id?: int|null, notes?: string|null}  $data
     */
    public function refuel(FuelStation $station, array $data, User $by): FuelTransaction
    {
        return DB::transaction(function () use ($station, $data, $by) {
            $station = $this->lock($station);
            $shift = $this->openShiftOf($station);
            $vehicle = Vehicle::lockForUpdate()->findOrFail($data['vehicle_id']);
            $amount = Quantity::toHundredths($data['amount']);
            $balance = Quantity::toHundredths($station->current_balance);

            if ($vehicle->fuel_type !== $station->fuel_type) {
                throw ValidationException::withMessages(['vehicle_id' => "Yoqilg'i turi mos emas"]);
            }

            if ($amount > $balance) {
                throw ValidationException::withMessages(['amount' => "Shahobchada yetarli yoqilg'i yo'q"]);
            }

            if ($vehicle->tank_capacity !== null && $amount > Quantity::toHundredths($vehicle->tank_capacity)) {
                throw ValidationException::withMessages(['amount' => "Bak hajmidan ko'p"]);
            }

            if ($vehicle->odometer !== null && (int) $data['odometer'] < $vehicle->odometer) {
                throw ValidationException::withMessages(['odometer' => "Spidometr ko'rsatkichi oldingisidan kam"]);
            }

            $waybill = self::openWaybillOf($vehicle);

            $transaction = $station->transactions()->create([
                'kind' => FuelTransaction::KIND_REFUEL,
                'type' => 'out',
                'fuel_shift_id' => $shift->id,
                'vehicle_id' => $vehicle->id,
                'waybill_id' => $waybill?->id,
                'driver_id' => $data['driver_id'] ?? $waybill?->driver_id ?? $vehicle->driver?->id,
                'odometer' => (int) $data['odometer'],
                'previous_odometer' => $vehicle->odometer,
                'amount' => Quantity::format($amount),
                'fuel_type' => $station->fuel_type,
                'notes' => $data['notes'] ?? null,
                'created_by' => $by->id,
            ]);

            $station->update(['current_balance' => Quantity::format($balance - $amount)]);
            $vehicle->update(['odometer' => (int) $data['odometer']]);

            return $transaction;
        });
    }

    public function cancel(FuelTransaction $transaction, string $reason, User $by): void
    {
        DB::transaction(function () use ($transaction, $reason, $by) {
            $station = $this->lock($transaction->fuelStation);
            $transaction->refresh();

            if (! in_array($transaction->kind, [FuelTransaction::KIND_RECEIPT, FuelTransaction::KIND_REFUEL], true)
                || $transaction->cancelled_at !== null || ! $transaction->shift?->isOpen()) {
                throw ValidationException::withMessages(['transaction' => "Smena yopilgan, bekor qilib bo'lmaydi"]);
            }

            $amount = Quantity::toHundredths($transaction->amount);
            $balance = Quantity::toHundredths($station->current_balance);
            $after = $transaction->kind === FuelTransaction::KIND_RECEIPT ? $balance - $amount : $balance + $amount;

            if ($after < 0) {
                throw ValidationException::withMessages(['transaction' => "Shahobchada yetarli yoqilg'i yo'q"]);
            }

            $transaction->update(['cancelled_at' => now(), 'cancelled_by' => $by->id, 'cancel_reason' => $reason]);
            $station->update(['current_balance' => Quantity::format($after)]);

            if ($transaction->kind === FuelTransaction::KIND_REFUEL && $transaction->vehicle_id !== null) {
                $vehicle = Vehicle::lockForUpdate()->findOrFail($transaction->vehicle_id);

                if ($vehicle->odometer === $transaction->odometer) {
                    $vehicle->update(['odometer' => $this->odometerWithout($transaction)]);
                }
            }
        });
    }

    /**
     * The vehicle's reading once a cancelled refuel no longer counts: the one it
     * replaced, or a refuel still standing at a higher reading.
     */
    private function odometerWithout(FuelTransaction $refuel): ?int
    {
        $standing = FuelTransaction::active()
            ->where('kind', FuelTransaction::KIND_REFUEL)
            ->where('vehicle_id', $refuel->vehicle_id)
            ->whereKeyNot($refuel->id)
            ->max('odometer');

        $readings = array_filter([$refuel->previous_odometer, $standing], fn ($reading) => $reading !== null);

        return $readings ? (int) max($readings) : null;
    }

    /**
     * Inventory act: set the book balance to the counted one. Only between shifts.
     *
     * @param  array{actual_balance: string|int|float, document_number: string, notes: string}  $data
     */
    public function adjust(FuelStation $station, array $data, User $by): FuelTransaction
    {
        return DB::transaction(function () use ($station, $data, $by) {
            $station = $this->lock($station);

            if ($station->openShift()->exists()) {
                throw ValidationException::withMessages(['shift' => "Inventarizatsiya smenalar orasida o'tkaziladi"]);
            }

            $actual = Quantity::toHundredths($data['actual_balance']);
            $difference = $actual - Quantity::toHundredths($station->current_balance);

            if ($difference === 0) {
                throw ValidationException::withMessages(['actual_balance' => 'Qoldiq hisobdagi bilan bir xil, akt kerak emas']);
            }

            $transaction = $station->transactions()->create([
                'kind' => FuelTransaction::KIND_ADJUSTMENT,
                'type' => $difference > 0 ? 'in' : 'out',
                'amount' => Quantity::format(abs($difference)),
                'fuel_type' => $station->fuel_type,
                'document_number' => $data['document_number'],
                'document_date' => now()->toDateString(),
                'notes' => $data['notes'],
                'created_by' => $by->id,
            ]);

            $station->update(['current_balance' => Quantity::format($actual)]);

            return $transaction;
        });
    }

    /**
     * The vehicle's waybill that is approved or under way, if any.
     */
    public static function openWaybillOf(Vehicle $vehicle): ?Waybill
    {
        return Waybill::where('vehicle_id', $vehicle->id)->whereIn('status', ['approved', 'in_progress'])->latest('id')->first();
    }

    private function openShiftOf(FuelStation $station): FuelShift
    {
        return $station->openShift()->first()
            ?? throw ValidationException::withMessages(['shift' => 'Smena ochilmagan']);
    }

    private function lock(FuelStation $station): FuelStation
    {
        return FuelStation::lockForUpdate()->findOrFail($station->id);
    }
}
