<?php

namespace App\Modules\Payments\Domain\Enums;

enum PaymentTerms: string
{
    case Prepaid = 'PREPAID';
    case Cod = 'COD';
}
