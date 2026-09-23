<?php

namespace App\Modules\Catalog\Domain\Enums;

enum ProductStatus: string
{
    case Draft = 'DRAFT';
    case Active = 'ACTIVE';
    case Archived = 'ARCHIVED';
}
