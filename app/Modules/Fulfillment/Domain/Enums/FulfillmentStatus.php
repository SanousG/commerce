<?php

namespace App\Modules\Fulfillment\Domain\Enums;

enum FulfillmentStatus: string
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Fulfilled = 'FULFILLED';
    case Cancelled = 'CANCELLED';
}
