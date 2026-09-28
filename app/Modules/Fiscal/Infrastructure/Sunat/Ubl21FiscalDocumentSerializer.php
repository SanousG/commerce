<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use App\Modules\Fiscal\Application\Contracts\FiscalDocumentSerializer;
use App\Modules\Fiscal\Application\FiscalPayload;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentType;
use App\Modules\Fiscal\Domain\Models\FiscalDocument;
use InvalidArgumentException;

final class Ubl21FiscalDocumentSerializer implements FiscalDocumentSerializer
{
    public function __construct(
        private PeruSunatUbl21InvoiceBuilder $invoice,
        private PeruSunatUbl21ReceiptBuilder $receipt,
        private SunatDocumentFileName $fileName,
    ) {}

    public function buildPayload(FiscalDocument $document): FiscalPayload
    {
        $contents = match ($document->document_type) {
            FiscalDocumentType::Invoice => $this->invoice->build($document),
            FiscalDocumentType::Receipt => $this->receipt->build($document),
            default => throw new InvalidArgumentException('SUNAT_DOCUMENT_TYPE_NOT_SUPPORTED'),
        };

        return new FiscalPayload($contents, 'application/xml', hash('sha256', $contents), $this->fileName->xml($document));
    }
}
