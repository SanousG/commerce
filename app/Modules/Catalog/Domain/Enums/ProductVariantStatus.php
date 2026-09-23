<?php

namespace App\Modules\Catalog\Domain\Enums;

enum ProductVariantStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Archived = 'ARCHIVED';
}
