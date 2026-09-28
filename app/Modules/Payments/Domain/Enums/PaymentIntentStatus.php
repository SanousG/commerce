<?php

namespace App\Modules\Payments\Domain\Enums;

enum PaymentIntentStatus: string
{
    case Pending = 'PENDING';
    case RequiresAction = 'REQUIRES_ACTION';
    case Processing = 'PROCESSING';
    case Authorized = 'AUTHORIZED';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';
    case Expired = 'EXPIRED';
}
