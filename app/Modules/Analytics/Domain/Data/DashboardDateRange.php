<?php

namespace App\Modules\Analytics\Domain\Data;

use App\Modules\Analytics\Domain\Enums\DashboardRange;
use Carbon\CarbonImmutable;
use DateTimeZone;

final readonly class DashboardDateRange
{
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

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'key' => $this->key->value,
            'label' => $this->label,
            'timezone' => $this->localStart->timezoneName,
            'local_start' => $this->localStart->toIso8601String(),
            'local_end_exclusive' => $this->localEnd->toIso8601String(),
            'utc_start' => $this->utcStart->toIso8601String(),
            'utc_end_exclusive' => $this->utcEnd->toIso8601String(),
        ];
    }
}
