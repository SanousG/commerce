<?php

namespace App\Modules\Procurement\Domain\Enums;

enum SupplierProductStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Archived = 'ARCHIVED';
}
