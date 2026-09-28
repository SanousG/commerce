<?php

namespace App\Modules\Payments\Domain\Enums;

enum PaymentStatus: string
{
    case Pending = 'PENDING';
    case RequiresAction = 'REQUIRES_ACTION';
    case Processing = 'PROCESSING';
    case Authorized = 'AUTHORIZED';
    case PartiallyCaptured = 'PARTIALLY_CAPTURED';
    case Captured = 'CAPTURED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';
    case Expired = 'EXPIRED';
}
