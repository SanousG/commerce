<?php

namespace App\Modules\Procurement\Domain\Enums;

enum SupplierStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Archived = 'ARCHIVED';
}
