<?php

namespace Tests\Unit\Analytics;

use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use App\Modules\Analytics\Domain\Enums\DashboardRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
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

    public function test_custom_range_is_inclusive_of_both_local_days_and_half_open_in_utc(): void
    {
        $range = DashboardDateRange::custom('2026-09-01', '2026-09-15', 'America/Lima');

        $this->assertSame(DashboardRange::Custom, $range->key);
        $this->assertSame('2026-09-01T00:00:00-05:00', $range->localStart->toIso8601String());
        $this->assertSame('2026-09-16T00:00:00-05:00', $range->localEnd->toIso8601String());
        $this->assertSame('2026-09-01T05:00:00+00:00', $range->utcStart->toIso8601String());
        $this->assertSame('2026-09-16T05:00:00+00:00', $range->utcEnd->toIso8601String());
        $this->assertSame('2026-09-15', $range->toArray()['local_end_inclusive']);
    }

    public function test_custom_range_rejects_reversed_and_oversized_windows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DashboardDateRange::custom('2026-09-15', '2026-09-01', 'America/Lima');
    }

    public function test_custom_range_rejects_windows_beyond_the_documented_maximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DashboardDateRange::custom('2020-01-01', '2026-01-01', 'America/Lima');
    }

    public function test_custom_range_allows_exactly_the_maximum_span(): void
    {
        $this->assertSame(366, DashboardDateRange::MAX_CUSTOM_DAYS);

        $range = DashboardDateRange::custom('2024-01-01', '2024-12-31', 'America/Lima');

        $this->assertSame(366, (int) $range->localStart->diffInDays($range->localEnd));
    }

    public function test_custom_range_rejects_one_day_beyond_the_maximum_span(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DashboardDateRange::custom('2024-01-01', '2025-01-01', 'America/Lima');
    }

    public function test_previous_window_has_identical_length_for_seven_days(): void
    {
        $range = DashboardDateRange::make(
            DashboardRange::Last7Days,
            'America/Lima',
            CarbonImmutable::parse('2026-09-27 18:00:00 UTC'),
        );
        $previous = $range->previous();

        $this->assertSame('2026-09-14', $previous->localStart->toDateString());
        $this->assertSame('2026-09-21', $previous->localEnd->toDateString());
        $this->assertSame($range->utcStart->toIso8601String(), $previous->utcEnd->toIso8601String());
    }

    public function test_previous_window_of_this_month_is_an_equal_length_window(): void
    {
        $range = DashboardDateRange::make(
            DashboardRange::ThisMonth,
            'America/Lima',
            CarbonImmutable::parse('2026-09-27 18:00:00 UTC'),
        );
        $previous = $range->previous();

        $currentDays = (int) $range->localStart->diffInDays($range->localEnd);
        $previousDays = (int) $previous->localStart->diffInDays($previous->localEnd);

        $this->assertSame(27, $currentDays);
        $this->assertSame($currentDays, $previousDays);
        $this->assertSame('2026-08-05', $previous->localStart->toDateString());
    }

    public function test_daylight_saving_transition_does_not_lose_or_gain_a_day(): void
    {
        $range = DashboardDateRange::make(
            DashboardRange::Today,
            'America/New_York',
            CarbonImmutable::parse('2026-03-08 12:00:00 UTC'),
        );

        $this->assertSame('2026-03-08T05:00:00+00:00', $range->utcStart->toIso8601String());
        $this->assertSame('2026-03-09T04:00:00+00:00', $range->utcEnd->toIso8601String());

        $previous = $range->previous();
        $this->assertSame('2026-03-07T05:00:00+00:00', $previous->utcStart->toIso8601String());
        $this->assertSame('2026-03-08T05:00:00+00:00', $previous->utcEnd->toIso8601String());
    }
}
