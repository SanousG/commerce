<?php

namespace App\Modules\Tax\Domain\Enums;

enum TaxCategoryCode: string
{
    case Standard = 'STANDARD';
    case Exempt = 'EXEMPT';
    case Unaffected = 'UNAFFECTED';
}
