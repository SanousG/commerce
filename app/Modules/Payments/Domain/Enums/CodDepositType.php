<?php

namespace App\Modules\Payments\Domain\Enums;

enum CodDepositType: string
{
    case None = 'NONE';
    case Percentage = 'PERCENTAGE';
    case FixedAmount = 'FIXED_AMOUNT';
}
