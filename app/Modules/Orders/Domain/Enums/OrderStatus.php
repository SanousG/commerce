<?php

namespace App\Modules\Orders\Domain\Enums;

enum OrderStatus: string
{
    case Pending = 'PENDING';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';
    case Completed = 'COMPLETED';
}
