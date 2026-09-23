<?php

namespace App\Modules\Cart\Domain\Enums;

enum CartStatus: string
{
    case Active = 'ACTIVE';
    case Converted = 'CONVERTED';
    case Expired = 'EXPIRED';
    case Abandoned = 'ABANDONED';
}
