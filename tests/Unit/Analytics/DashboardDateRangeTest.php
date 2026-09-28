<?php

namespace Tests\Unit\Analytics;

use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use App\Modules\Analytics\Domain\Enums\DashboardRange;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DashboardDateRangeTest extends TestCase
{
    public function test_today_uses_store_timezone_and_exposes_utc_boundaries(): void
    {
        $range = DashboardDateRange::make(
            DashboardRange::Today,
            'America/Lima',
            CarbonImmutable::parse('2026-09-27 03:00:00 UTC'),
        );

        $this->assertSame('2026-09-26T00:00:00-05:00', $range->localStart->toIso8601String());
        $this->assertSame('2026-09-27T00:00:00-05:00', $range->localEnd->toIso8601String());
        $this->assertSame('2026-09-26T05:00:00+00:00', $range->utcStart->toIso8601String());
        $this->assertSame('2026-09-27T05:00:00+00:00', $range->utcEnd->toIso8601String());
    }

    public function test_last_seven_days_is_seven_complete_store_calendar_days_including_today(): void
    {
        $range = DashboardDateRange::make(
            DashboardRange::Last7Days,
            'America/Lima',
            CarbonImmutable::parse('2026-09-27 18:00:00 UTC'),
        );

        $this->assertSame('2026-09-21', $range->localStart->toDateString());
        $this->assertSame('2026-09-28', $range->localEnd->toDateString());
        $this->assertEquals(7, $range->localStart->diffInDays($range->localEnd));
    }
}
