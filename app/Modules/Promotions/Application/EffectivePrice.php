<?php

namespace App\Modules\Promotions\Application;

use App\Modules\Promotions\Domain\Models\Promotion;

readonly class EffectivePrice
{
    public function __construct(
        public string $baseAmount,
        public string $finalAmount,
        public string $totalDiscount,
        public ?Promotion $promotion,
    ) {}

    public function toArray(): array
    {
        return [
            'base_amount' => $this->baseAmount,
            'final_amount' => $this->finalAmount,
            'total_discount' => $this->totalDiscount,
            'applied_promotions' => $this->promotion === null ? [] : [[
                'id' => $this->promotion->getKey(),
                'name' => $this->promotion->name,
                'internal_code' => $this->promotion->internal_code,
            ]],
        ];
    }
}
