<?php

namespace Tests\Unit\Hr;

use App\Support\Hr\DueDates;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class DueDatesTest extends TestCase
{
    public function test_medical_next_due_adds_months_without_overflow(): void
    {
        $this->assertSame('2026-02-28', DueDates::medicalNextDue(Carbon::parse('2026-01-31'), 1, 'fit')->toDateString());
        $this->assertSame('2027-01-10', DueDates::medicalNextDue(Carbon::parse('2026-01-10'), 12, 'fit_with_restrictions')->toDateString());
    }

    public function test_medical_next_due_is_null_for_unfit_or_no_periodicity(): void
    {
        $this->assertNull(DueDates::medicalNextDue(Carbon::parse('2026-01-10'), 12, 'unfit'));
        $this->assertNull(DueDates::medicalNextDue(Carbon::parse('2026-01-10'), null, 'fit'));
    }

    public function test_briefing_next_due_only_for_resetting_types(): void
    {
        foreach (['primary', 'repeated', 'unscheduled'] as $type) {
            $this->assertSame('2026-07-10', DueDates::briefingNextDue(Carbon::parse('2026-01-10'), $type, 6)->toDateString());
        }

        $this->assertNull(DueDates::briefingNextDue(Carbon::parse('2026-01-10'), 'targeted', 6));
        $this->assertNull(DueDates::briefingNextDue(Carbon::parse('2026-01-10'), 'introductory', 6));
        $this->assertNull(DueDates::briefingNextDue(Carbon::parse('2026-01-10'), 'repeated', null));
    }

    public function test_ppe_due(): void
    {
        $this->assertSame('2027-01-10', DueDates::ppeDue(Carbon::parse('2026-01-10'), 12)->toDateString());
        $this->assertNull(DueDates::ppeDue(Carbon::parse('2026-01-10'), null));
    }

    public function test_absence_days_inclusive(): void
    {
        $this->assertSame(1, DueDates::absenceDays(Carbon::parse('2026-03-01'), Carbon::parse('2026-03-01')));
        $this->assertSame(14, DueDates::absenceDays(Carbon::parse('2026-03-01'), Carbon::parse('2026-03-14')));
    }
}
