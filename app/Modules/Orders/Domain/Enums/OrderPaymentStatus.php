<?php

namespace App\Modules\Orders\Domain\Enums;

enum OrderPaymentStatus: string
{
    case Unpaid = 'UNPAID';
    case PartiallyPaid = 'PARTIALLY_PAID';
    case Paid = 'PAID';
    case Refunded = 'REFUNDED';
    case PartiallyRefunded = 'PARTIALLY_REFUNDED';
}
