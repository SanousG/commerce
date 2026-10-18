<?php

namespace App\Modules\Analytics\Domain\Data;

use App\Modules\Analytics\Domain\Enums\DashboardRange;
use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class DashboardDateRange
{
    /**
     * Upper bound for a custom range, in inclusive store calendar days.
     * Bounds the aggregate queries and keeps the daily evolution series readable.
     */
    public const MAX_CUSTOM_DAYS = 366;

    public function __construct(
        public DashboardRange $key,
        public CarbonImmutable $localStart,
        public CarbonImmutable $localEnd,
        public CarbonImmutable $utcStart,
        public CarbonImmutable $utcEnd,
        public string $label,
    ) {}

    public static function make(
        DashboardRange $range,
        string $timezone,
        ?CarbonImmutable $now = null,
    ): self {
        $zone = new DateTimeZone($timezone);
        $localNow = ($now ?? CarbonImmutable::now($zone))->setTimezone($zone);
        $today = $localNow->startOfDay();

        [$start, $end, $label] = match ($range) {
            DashboardRange::Today => [$today, $today->addDay(), 'Today'],
            DashboardRange::Yesterday => [$today->subDay(), $today, 'Yesterday'],
            DashboardRange::Last7Days => [$today->subDays(6), $today->addDay(), 'Last 7 days'],
            DashboardRange::Last30Days => [$today->subDays(29), $today->addDay(), 'Last 30 days'],
            DashboardRange::ThisMonth => [$today->startOfMonth(), $today->addDay(), 'This month'],
            DashboardRange::Custom => throw new InvalidArgumentException(
                'A custom range must be built with DashboardDateRange::custom().'
            ),
        };

        return new self(
            $range,
            $start,
            $end,
            $start->utc(),
            $end->utc(),
            $label,
        );
    }

    /**
     * Builds an inclusive custom range from store-local calendar dates (Y-m-d).
     * The exclusive end is the next store-local midnight, so the whole `to` day
     * is included without losing trailing microseconds.
     */
    public static function custom(string $from, string $to, string $timezone): self
    {
        $zone = new DateTimeZone($timezone);
        $start = CarbonImmutable::createFromFormat('Y-m-d', $from, $zone);
        $endInclusive = CarbonImmutable::createFromFormat('Y-m-d', $to, $zone);

        if ($start === false || $endInclusive === false) {
            throw new InvalidArgumentException('A custom range requires valid Y-m-d dates.');
        }

        $start = $start->startOfDay();
        $endInclusive = $endInclusive->startOfDay();

        if ($endInclusive->lessThan($start)) {
            throw new InvalidArgumentException('The custom range start must not be after its end.');
        }

        if (self::dayCount($start, $endInclusive) + 1 > self::MAX_CUSTOM_DAYS) {
            throw new InvalidArgumentException(
                'The custom range must not exceed '.self::MAX_CUSTOM_DAYS.' days.'
            );
        }

        $end = $endInclusive->addDay();

        return new self(
            DashboardRange::Custom,
            $start,
            $end,
            $start->utc(),
            $end->utc(),
            $start->toDateString().' to '.$endInclusive->toDateString(),
        );
    }

    /**
     * Returns the immediately preceding window of identical calendar length.
     * Comparing equal-length windows keeps a period-over-period variation honest.
     */
    public function previous(): self
    {
        $days = max(1, self::dayCount($this->localStart, $this->localEnd));
        $start = $this->localStart->subDays($days);
        $end = $this->localStart;

        return new self(
            $this->key,
            $start,
            $end,
            $start->utc(),
            $end->utc(),
            'Previous '.$this->label,
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'key' => $this->key->value,
            'label' => $this->label,
            'timezone' => $this->localStart->timezoneName,
            'local_start' => $this->localStart->toIso8601String(),
            'local_end_inclusive' => $this->localEnd->subDay()->toDateString(),
            'local_end_exclusive' => $this->localEnd->toIso8601String(),
            'utc_start' => $this->utcStart->toIso8601String(),
            'utc_end_exclusive' => $this->utcEnd->toIso8601String(),
        ];
    }

    /**
     * Whole store calendar days between two store-local midnights, ignoring any
     * daylight-saving offset so a range never gains or loses a day.
     */
    private static function dayCount(CarbonImmutable $start, CarbonImmutable $end): int
    {
        $utcStart = CarbonImmutable::createFromFormat('Y-m-d', $start->toDateString(), 'UTC');
        $utcEnd = CarbonImmutable::createFromFormat('Y-m-d', $end->toDateString(), 'UTC');

        return (int) $utcStart->diffInDays($utcEnd);
    }
}
