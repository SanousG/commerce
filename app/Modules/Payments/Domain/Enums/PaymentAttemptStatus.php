<?php

namespace App\Modules\Payments\Domain\Enums;

enum PaymentAttemptStatus: string
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';
}
