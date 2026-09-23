<?php

namespace App\Modules\Pricing\Domain\Enums;

enum PriceListStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Archived = 'ARCHIVED';
}
