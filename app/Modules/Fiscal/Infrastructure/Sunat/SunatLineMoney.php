<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use App\Modules\Tax\Domain\Enums\TaxCategoryCode;
use Brick\Math\BigDecimal;

/** Per-line monetary values after SUNAT-compatible 2-decimal rounding. */
final readonly class SunatLineMoney
{
    public function __construct(
        public int $position,
        public BigDecimal $extension,
        public BigDecimal $tax,
        public BigDecimal $total,
        public TaxCategoryCode $category,
        public ?string $rate,
    ) {}
}
