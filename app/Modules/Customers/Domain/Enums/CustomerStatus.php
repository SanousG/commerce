<?php

namespace App\Modules\Customers\Domain\Enums;

enum CustomerStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Blocked = 'BLOCKED';
    case Archived = 'ARCHIVED';
}
