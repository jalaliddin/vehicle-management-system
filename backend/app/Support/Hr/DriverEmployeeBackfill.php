<?php

namespace App\Support\Hr;

use App\Models\Driver;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

/**
 * Gives every driver recorded before the HR module an employee record.
 */
class DriverEmployeeBackfill
{
    public const DRIVER_POSITION = 'Haydovchi';

    /**
     * Link each driver without an employee to one, creating it when no
     * employee with the same PINFL exists. Returns how many were linked.
     */
    public static function run(): int
    {
        $drivers = Driver::whereNull('employee_id')->get();

        if ($drivers->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($drivers) {
            $position = Position::firstOrCreate(['name' => self::DRIVER_POSITION]);

            foreach ($drivers as $driver) {
                $employee = Employee::firstWhere('pinfl', $driver->pinfl)
                    ?? Employee::create(array_merge(self::splitName($driver->full_name), [
                        'organization_id' => $driver->organization_id,
                        'position_id' => $position->id,
                        'pinfl' => $driver->pinfl,
                        'phone' => $driver->phone,
                        'employment_type' => $driver->employment_type ?? 'staff',
                        'status' => 'active',
                    ]));

                $driver->update(['employee_id' => $employee->id]);
            }

            return $drivers->count();
        });
    }

    /**
     * "Last First Middle..." → its parts; a single word becomes the last name.
     *
     * @return array{last_name: string, first_name: string, middle_name: string|null}
     */
    public static function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);

        return [
            'last_name' => $parts[0] ?? '',
            'first_name' => $parts[1] ?? '',
            'middle_name' => count($parts) > 2 ? implode(' ', array_slice($parts, 2)) : null,
        ];
    }
}
