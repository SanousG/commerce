<?php

namespace App\Modules\Returns\Domain\Enums;

enum ExchangeStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
