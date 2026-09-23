<?php

namespace App\Modules\Catalog\Domain\Enums;

enum CategoryStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Archived = 'ARCHIVED';
}
