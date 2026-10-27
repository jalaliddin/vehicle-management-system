<?php

namespace App\Services\Hr;

use App\Models\Driver;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Whether a driver may go out on the given day, and if not, why.
 */
class DriverClearance
{
    private const MESSAGES = [
        'no_employee' => "Haydovchi xodimlar bazasiga bog'lanmagan",
        'employee_dismissed' => "Xodim ishdan bo'shatilgan",
        'driver_inactive' => 'Haydovchi faol emas',
        'license_expired' => 'Haydovchilik guvohnomasi muddati tugagan',
        'medical_missing' => "Tibbiy ko'rik o'tkazilmagan",
        'medical_overdue' => "Tibbiy ko'rik muddati o'tgan",
        'medical_unfit' => 'Tibbiy xulosa: yaroqsiz',
        'briefing_overdue' => "Yo'riqnoma muddati o'tgan",
        'absent' => "Xodim bu sanada ishda emas (ta'til, kasallik va h.k.)",
    ];

    /**
     * @return array{admitted: bool, reasons: list<array{code: string, message: string}>}
     */
    public function check(Driver $driver, ?CarbonInterface $on = null): array
    {
        $on = CarbonImmutable::parse($on ?? today())->startOfDay();
        $codes = [];

        if ($driver->status === 'inactive') {
            $codes[] = 'driver_inactive';
        }

        if ($driver->license_expiry !== null && $driver->license_expiry->lt($on)) {
            $codes[] = 'license_expired';
        }

        $employee = $driver->employee;

        if ($employee === null) {
            $codes[] = 'no_employee';
        } else {
            if (! $employee->isActive()) {
                $codes[] = 'employee_dismissed';
            }

            $exam = $employee->latestMedicalExam;

            if ($exam === null) {
                if ($employee->position?->medical_exam_months !== null) {
                    $codes[] = 'medical_missing';
                }
            } elseif ($exam->result === 'unfit') {
                $codes[] = 'medical_unfit';
            } elseif ($exam->next_due_date !== null && $exam->next_due_date->lt($on)) {
                $codes[] = 'medical_overdue';
            }

            if ($employee->position?->briefing_months !== null) {
                $due = $employee->briefings()->whereNotNull('safety_briefing_participants.next_due_date')
                    ->max('safety_briefing_participants.next_due_date');

                if ($due === null || CarbonImmutable::parse($due)->lt($on)) {
                    $codes[] = 'briefing_overdue';
                }
            }

            if ($employee->isAbsentOn($on)) {
                $codes[] = 'absent';
            }
        }

        return [
            'admitted' => $codes === [],
            'reasons' => array_map(fn (string $code) => ['code' => $code, 'message' => self::MESSAGES[$code]], $codes),
        ];
    }
}
