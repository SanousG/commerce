<?php

namespace App\Modules\Procurement\Domain\Enums;

enum PurchaseReceiptStatus: string
{
    case Draft = 'DRAFT';
    case Posted = 'POSTED';
    case Cancelled = 'CANCELLED';
}
