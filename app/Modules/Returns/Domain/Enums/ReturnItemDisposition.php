<?php

namespace App\Modules\Returns\Domain\Enums;

enum ReturnItemDisposition: string
{
    case Restock = 'RESTOCK';
    case Scrap = 'SCRAP';
    case Quarantine = 'QUARANTINE';
    case ReturnToVendor = 'RETURN_TO_VENDOR';
    case NoAction = 'NO_ACTION';
}
