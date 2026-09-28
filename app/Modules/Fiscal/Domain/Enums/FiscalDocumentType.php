<?php

namespace App\Modules\Fiscal\Domain\Enums;

enum FiscalDocumentType: string
{
    case Invoice = 'INVOICE';
    case Receipt = 'RECEIPT';
    case CreditNote = 'CREDIT_NOTE';
}
