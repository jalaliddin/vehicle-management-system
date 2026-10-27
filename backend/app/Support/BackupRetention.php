<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Which backup sets to delete: keep every set of the last days, plus the first
 * set of each of the last calendar months.
 */
class BackupRetention
{
    /**
     * @param  array<string, CarbonInterface>  $backups  Set name => when it was taken.
     * @return list<string> Names of the sets to delete, oldest first.
     */
    public static function toDelete(array $backups, CarbonInterface $now, int $keepDays, int $keepMonths): array
    {
        asort($backups);

        $dailyCutoff = $now->copy()->subDays($keepDays);
        $monthlyCutoff = $now->copy()->startOfMonth()->subMonths($keepMonths - 1);
        $firstOfMonth = [];

        foreach ($backups as $name => $takenAt) {
            $month = $takenAt->format('Y-m');

            if ($takenAt->gte($monthlyCutoff) && ! isset($firstOfMonth[$month])) {
                $firstOfMonth[$month] = $name;
            }
        }

        $keep = array_flip($firstOfMonth);

        return array_values(array_filter(
            array_keys($backups),
            fn (string $name) => ! isset($keep[$name]) && $backups[$name]->lte($dailyCutoff),
        ));
    }
}
