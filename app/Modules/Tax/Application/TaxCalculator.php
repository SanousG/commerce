<?php

namespace App\Modules\Tax\Application;

use App\Modules\Tax\Domain\Enums\TaxCategoryCode;
use App\Modules\Tax\Domain\Models\TaxCategory;
use App\Modules\Tax\Domain\Models\TaxRate;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class TaxCalculator
{
    public function calculateInclusive(string $amount, TaxCategory $category, TaxRate $rate): TaxCalculation
    {
        $total = BigDecimal::of($amount)->toScale(4, RoundingMode::HalfUp);
        $percentage = BigDecimal::of($rate->rate);

        if ($total->isNegative()) {
            throw ValidationException::withMessages(['amount' => 'The tax-inclusive amount cannot be negative.']);
        }

        if ($category->code !== TaxCategoryCode::Standard) {
            if (! $percentage->isZero()) {
                throw ValidationException::withMessages([
                    'tax_rate' => 'Exempt and unaffected categories require a zero tax rate.',
                ]);
            }

            return new TaxCalculation((string) $total, '0.0000', (string) $total, $category->code, (string) $percentage->toScale(6));
        }

        $divisor = BigDecimal::one()->plus($percentage->dividedBy(100, 10, RoundingMode::HalfUp));
        $base = $total->dividedBy($divisor, 4, RoundingMode::HalfUp);
        $tax = $total->minus($base)->toScale(4, RoundingMode::HalfUp);

        return new TaxCalculation(
            (string) $base,
            (string) $tax,
            (string) $total,
            $category->code,
            (string) $percentage->toScale(6),
        );
    }
}
