<?php

namespace App\Modules\Promotions\Domain\Enums;

enum PromotionDiscountType: string
{
    case Percentage = 'PERCENTAGE';
    case FixedAmount = 'FIXED_AMOUNT';
}
