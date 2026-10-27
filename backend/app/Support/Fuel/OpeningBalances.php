<?php

namespace App\Support\Fuel;

use App\Models\FuelStation;
use App\Models\FuelTransaction;

/**
 * Records each station's balance from before the ledger as an opening
 * adjustment, so turnover statements add up from the first day.
 */
class OpeningBalances
{
    /**
     * Returns how many opening rows were written; stations that already have
     * one, or hold no fuel, are skipped.
     */
    public static function record(): int
    {
        $stations = FuelStation::where('current_balance', '>', 0)
            ->whereDoesntHave('transactions', fn ($q) => $q->where('document_number', FuelTransaction::OPENING_DOCUMENT))
            ->get();

        foreach ($stations as $station) {
            $station->transactions()->create([
                'kind' => FuelTransaction::KIND_ADJUSTMENT,
                'type' => 'in',
                'amount' => $station->current_balance,
                'fuel_type' => $station->fuel_type,
                'document_number' => FuelTransaction::OPENING_DOCUMENT,
                'notes' => "Tizimga o'tishdagi qoldiq",
            ]);
        }

        return $stations->count();
    }
}
