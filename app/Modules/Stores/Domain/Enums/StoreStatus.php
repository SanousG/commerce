<?php

namespace App\Modules\Stores\Domain\Enums;

enum StoreStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Archived = 'ARCHIVED';
}
