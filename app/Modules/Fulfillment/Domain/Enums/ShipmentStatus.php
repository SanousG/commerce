<?php

namespace App\Modules\Fulfillment\Domain\Enums;

enum ShipmentStatus: string
{
    case Pending = 'PENDING';
    case Ready = 'READY';
    case Shipped = 'SHIPPED';
    case Delivered = 'DELIVERED';
    case Cancelled = 'CANCELLED';
}
