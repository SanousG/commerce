<?php

namespace App\Modules\Payments\Domain\Enums;

enum PaymentTransactionType: string
{
    case Capture = 'CAPTURE';
    case Authorize = 'AUTHORIZE';
    case Void = 'VOID';
    case Refund = 'REFUND';
}
