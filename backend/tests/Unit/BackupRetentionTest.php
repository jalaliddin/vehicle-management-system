<?php

namespace Tests\Unit;

use App\Support\BackupRetention;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class BackupRetentionTest extends TestCase
{
    /**
     * Daily backups at 02:00 from 2025-09-01 to 2026-10-21.
     *
     * @return array<string, CarbonImmutable>
     */
    private function dailySets(): array
    {
        $sets = [];

        for ($day = CarbonImmutable::parse('2025-09-01 02:00'); $day->lte('2026-10-21 02:00'); $day = $day->addDay()) {
            $sets[$day->format('Ymd-His')] = $day;
        }

        return $sets;
    }

    public function test_keeps_last_30_days_and_first_of_each_of_12_months(): void
    {
        $sets = $this->dailySets();

        $deleted = BackupRetention::toDelete($sets, CarbonImmutable::parse('2026-10-21 03:00'), 30, 12);
        $kept = array_diff(array_keys($sets), $deleted);

        $monthly = ['20251101-020000', '20251201-020000', '20260101-020000', '20260201-020000', '20260301-020000',
            '20260401-020000', '20260501-020000', '20260601-020000', '20260701-020000', '20260801-020000',
            '20260901-020000', '20261001-020000'];

        foreach ($monthly as $name) {
            $this->assertContains($name, $kept);
        }

        $this->assertContains('20260922-020000', $kept);
        $this->assertNotContains('20260920-020000', $kept);
        $this->assertNotContains('20251001-020000', $kept);
        $this->assertCount(30 + 11, $kept);
    }

    public function test_deletes_extra_sets_within_a_month(): void
    {
        $sets = [
            '20260301-020000' => CarbonImmutable::parse('2026-03-01 02:00'),
            '20260315-020000' => CarbonImmutable::parse('2026-03-15 02:00'),
            '20260331-020000' => CarbonImmutable::parse('2026-03-31 02:00'),
        ];

        $this->assertSame(
            ['20260315-020000', '20260331-020000'],
            BackupRetention::toDelete($sets, CarbonImmutable::parse('2026-10-21'), 30, 12),
        );
    }
}
