<?php

namespace App\Modules\Orders\Domain\Enums;

enum OrderFulfillmentStatus: string
{
    case Unfulfilled = 'UNFULFILLED';
    case PartiallyFulfilled = 'PARTIALLY_FULFILLED';
    case Fulfilled = 'FULFILLED';
}
