<?php

namespace App\Modules\Procurement\Domain\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'DRAFT';
    case Approved = 'APPROVED';
    case Ordered = 'ORDERED';
    case PartiallyReceived = 'PARTIALLY_RECEIVED';
    case Received = 'RECEIVED';
    case Cancelled = 'CANCELLED';
}
