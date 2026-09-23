<?php

namespace App\Modules\Promotions\Domain\Enums;

enum PromotionStatus: string
{
    case Draft = 'DRAFT';
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Archived = 'ARCHIVED';
}
