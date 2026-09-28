<?php

namespace App\Modules\Analytics\Domain\Data;

final readonly class AdminDashboardData
{
    /**
     * @param  array<string, mixed>  $sections
     * @param  list<array<string, mixed>>  $attention
     */
    public function __construct(
        public DashboardDateRange $range,
        public string $currency,
        public array $sections,
        public array $attention,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'range' => $this->range->toArray(),
            'currency' => $this->currency,
            'sections' => $this->sections,
            'attention' => $this->attention,
        ];
    }
}
