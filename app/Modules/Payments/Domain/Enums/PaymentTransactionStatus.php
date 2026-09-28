<?php

namespace App\Modules\Payments\Domain\Enums;

enum PaymentTransactionStatus: string
{
    case Pending = 'PENDING';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
}
