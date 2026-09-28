<?php

namespace App\Modules\Payments\Domain\Enums;

enum RefundStatus: string
{
    case Pending = 'PENDING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';
}
