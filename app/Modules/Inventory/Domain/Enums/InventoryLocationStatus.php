<?php

namespace App\Modules\Inventory\Domain\Enums;

enum InventoryLocationStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Archived = 'ARCHIVED';
}
