<?php

namespace App\Modules\Inventory\Domain\Enums;

enum InventoryReservationStatus: string
{
    case Active = 'ACTIVE';
    case Released = 'RELEASED';
    case Committed = 'COMMITTED';
    case Expired = 'EXPIRED';
}
