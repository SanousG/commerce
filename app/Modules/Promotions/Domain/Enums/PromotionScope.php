<?php

namespace App\Modules\Promotions\Domain\Enums;

enum PromotionScope: string
{
    case All = 'ALL';
    case Selected = 'SELECTED';
}
