<?php

namespace App\Modules\Tax\Domain\Enums;

enum TaxCategoryStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
}
