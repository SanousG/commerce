<?php

namespace App\Modules\Checkout\Domain\Enums;

enum CheckoutStatus: string
{
    case Pending = 'PENDING';
    case Ready = 'READY';
    case Completed = 'COMPLETED';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';
    case Failed = 'FAILED';
}
