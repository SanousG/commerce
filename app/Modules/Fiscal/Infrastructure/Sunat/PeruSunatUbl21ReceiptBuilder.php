<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use App\Modules\Fiscal\Domain\Enums\FiscalDocumentType;
use App\Modules\Fiscal\Domain\Models\FiscalDocument;

final class PeruSunatUbl21ReceiptBuilder
{
    public function __construct(private Ubl21InvoiceXmlFactory $factory) {}

    public function build(FiscalDocument $document): string
    {
        return $this->factory->build($document, FiscalDocumentType::Receipt);
    }
}
