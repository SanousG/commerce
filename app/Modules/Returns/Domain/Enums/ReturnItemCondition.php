<?php

namespace App\Modules\Returns\Domain\Enums;

enum ReturnItemCondition: string
{
    case Sellable = 'SELLABLE';
    case Damaged = 'DAMAGED';
    case Defective = 'DEFECTIVE';
    case OpenBox = 'OPEN_BOX';
    case Unknown = 'UNKNOWN';
}
