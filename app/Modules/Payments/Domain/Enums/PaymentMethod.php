<?php

namespace App\Modules\Payments\Domain\Enums;

enum PaymentMethod: string
{
    case Online = 'ONLINE';
    case BankTransfer = 'BANK_TRANSFER';
    case Cash = 'CASH';
}
