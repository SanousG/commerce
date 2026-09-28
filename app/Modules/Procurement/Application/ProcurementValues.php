<?php

namespace App\Modules\Procurement\Application;

use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ProcurementValues
{
    public static function amount(string|int|float $value, string $field = 'unit_cost'): string
    {
        try {
            $amount = BigDecimal::of((string) $value)->toScale(4);
        } catch (Throwable) {
            throw ValidationException::withMessages([$field => 'The amount must have no more than four decimal places.']);
        }
        if ($amount->isNegative()) {
            throw ValidationException::withMessages([$field => 'The amount cannot be negative.']);
        }

        return (string) $amount;
    }

    public static function currency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw ValidationException::withMessages(['currency' => 'The currency must be a three-letter ISO code.']);
        }

        return $currency;
    }

    public static function positiveQuantity(int $quantity, string $field = 'quantity'): int
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([$field => 'The quantity must be positive.']);
        }

        return $quantity;
    }
}
