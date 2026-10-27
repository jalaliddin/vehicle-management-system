<?php

namespace App\Support\Fuel;

/**
 * Fuel quantities as integer hundredths, so balances never pick up float residue.
 */
class Quantity
{
    /**
     * "33.335" → 3334, 12 → 1200, "0.1" → 10 (half up, two decimals).
     */
    public static function toHundredths(string|int|float $value): int
    {
        if (is_int($value)) {
            return $value * 100;
        }

        if (is_float($value)) {
            return (int) round($value * 100);
        }

        $value = trim($value);
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '+-'), 2), 2, '');
        $fraction = str_pad($fraction, 3, '0');

        $hundredths = (int) $whole * 100 + (int) substr($fraction, 0, 2) + ((int) $fraction[2] >= 5 ? 1 : 0);

        return $negative ? -$hundredths : $hundredths;
    }

    /**
     * 125050 → "1250.50".
     */
    public static function format(int $hundredths): string
    {
        $sign = $hundredths < 0 ? '-' : '';
        $hundredths = abs($hundredths);

        return $sign.intdiv($hundredths, 100).'.'.str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT);
    }
}
