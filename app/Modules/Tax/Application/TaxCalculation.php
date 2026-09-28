<?php

namespace App\Modules\Tax\Application;

use App\Modules\Tax\Domain\Enums\TaxCategoryCode;

final readonly class TaxCalculation
{
    public function __construct(
        public string $baseAmount,
        public string $taxAmount,
        public string $total,
        public TaxCategoryCode $categoryCode,
        public string $rate,
    ) {}
}
