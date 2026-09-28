<?php

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Domain\Enums\CodDepositType;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class CalculateCodDeposit
{
    /** @return array{deposit_type: CodDepositType, deposit_value: string, amount_due_now: string, amount_due_later: string} */
    public function calculate(string $totalAmount, CodDepositType $type, string $value): array
    {
        $total = BigDecimal::of($totalAmount)->toScale(4);
        $depositValue = BigDecimal::of($value)->toScale(4);
        $dueNow = match ($type) {
            CodDepositType::None => BigDecimal::zero()->toScale(4),
            CodDepositType::Percentage => $total->multipliedBy($depositValue)
                ->dividedBy(100, 4, RoundingMode::HalfUp),
            CodDepositType::FixedAmount => $depositValue,
        };
        if ($dueNow->isGreaterThan($total)) {
            throw ValidationException::withMessages(['payment_terms' => 'The COD deposit cannot exceed the order total.']);
        }

        return [
            'deposit_type' => $type,
            'deposit_value' => (string) $depositValue,
            'amount_due_now' => (string) $dueNow,
            'amount_due_later' => (string) $total->minus($dueNow),
        ];
    }
}
