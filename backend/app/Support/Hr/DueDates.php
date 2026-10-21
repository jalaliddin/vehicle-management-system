<?php

namespace App\Support\Hr;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Deadline rules for medical exams, briefings, PPE and absences.
 */
class DueDates
{
    /**
     * Briefing types that restart the repeated-briefing clock.
     */
    public const RESETTING_BRIEFINGS = ['primary', 'repeated', 'unscheduled'];

    public static function medicalNextDue(CarbonInterface $examDate, ?int $months, string $result): ?CarbonImmutable
    {
        if ($months === null || $result === 'unfit') {
            return null;
        }

        return CarbonImmutable::parse($examDate)->addMonthsNoOverflow($months);
    }

    public static function briefingNextDue(CarbonInterface $date, string $type, ?int $months): ?CarbonImmutable
    {
        if ($months === null || ! in_array($type, self::RESETTING_BRIEFINGS, true)) {
            return null;
        }

        return CarbonImmutable::parse($date)->addMonthsNoOverflow($months);
    }

    public static function ppeDue(CarbonInterface $issuedOn, ?int $months): ?CarbonImmutable
    {
        return $months === null ? null : CarbonImmutable::parse($issuedOn)->addMonthsNoOverflow($months);
    }

    /**
     * Calendar days from start to end, both included.
     */
    public static function absenceDays(CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) CarbonImmutable::parse($start)->startOfDay()->diffInDays(CarbonImmutable::parse($end)->startOfDay()) + 1;
    }
}
