<?php

namespace App\Support\Fuel;

/**
 * Consumption estimate from refuels alone: the fuel added after the first
 * reading burned the distance up to the last one. Assumes full tanks.
 */
class Consumption
{
    /**
     * @param  list<array{odometer: int, amount: string|int|float}>  $refuels  Ordered by odometer, then time.
     * @return array{first_odometer: int, last_odometer: int, km: int, litres: string, per_100km: float}|null
     */
    public static function estimate(array $refuels): ?array
    {
        if (count($refuels) < 2) {
            return null;
        }

        $first = $refuels[0]['odometer'];
        $last = $refuels[count($refuels) - 1]['odometer'];
        $km = $last - $first;

        if ($km <= 0) {
            return null;
        }

        $hundredths = array_sum(array_map(fn (array $refuel) => Quantity::toHundredths($refuel['amount']), array_slice($refuels, 1)));

        return [
            'first_odometer' => $first,
            'last_odometer' => $last,
            'km' => $km,
            'litres' => Quantity::format($hundredths),
            'per_100km' => round($hundredths / $km, 2),
        ];
    }
}
