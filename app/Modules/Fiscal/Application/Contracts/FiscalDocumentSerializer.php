<?php

namespace App\Modules\Fiscal\Application\Contracts;

use App\Modules\Fiscal\Application\FiscalPayload;
use App\Modules\Fiscal\Domain\Models\FiscalDocument;

interface FiscalDocumentSerializer
{
    public function buildPayload(FiscalDocument $document): FiscalPayload;
}
