<?php

namespace App\Services\Hr;

use App\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * Annual leave per calendar year: entitlement minus actual annual-leave days
 * that fall in the year.
 */
class LeaveBalance
{
    /**
     * @return array{entitlement: int, used: int, remaining: int}
     */
    public static function forYear(Employee $employee, int $year): array
    {
        $yearStart = CarbonImmutable::create($year)->startOfYear();
        $yearEnd = $yearStart->endOfYear()->startOfDay();

        $used = $employee->absences()
            ->where('type', 'annual_leave')
            ->where('is_planned', false)
            ->whereDate('start_date', '<=', $yearEnd)
            ->whereDate('end_date', '>=', $yearStart)
            ->get()
            ->sum(function ($absence) use ($yearStart, $yearEnd) {
                $start = $absence->start_date->max($yearStart);
                $end = $absence->end_date->min($yearEnd);

                return (int) $start->startOfDay()->diffInDays($end->startOfDay()) + 1;
            });

        return [
            'entitlement' => $employee->annual_leave_days,
            'used' => $used,
            'remaining' => $employee->annual_leave_days - $used,
        ];
    }
}
