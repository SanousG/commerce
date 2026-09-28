<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use App\Modules\Fiscal\Domain\Models\FiscalDocument;
use App\Modules\Fiscal\Infrastructure\Sunat\Catalogs\SunatCatalog;
use InvalidArgumentException;

final class SunatDocumentFileName
{
    public function base(FiscalDocument $document): string
    {
        $base = implode('-', [
            $document->issuer_tax_id,
            SunatCatalog::documentType($document->document_type),
            $document->series,
            (string) $document->sequence_number,
        ]);

        if (! preg_match('/^[0-9]{11}-(01-F[A-Z0-9]{3}|03-B[A-Z0-9]{3})-[0-9]{1,8}$/', $base)) {
            throw new InvalidArgumentException('SUNAT_DOCUMENT_FILE_NAME_INVALID');
        }

        return $base;
    }

    public function xml(FiscalDocument $document): string
    {
        return $this->base($document).'.xml';
    }

    public function zip(FiscalDocument $document): string
    {
        return $this->base($document).'.zip';
    }
}
