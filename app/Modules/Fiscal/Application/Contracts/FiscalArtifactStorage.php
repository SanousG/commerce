<?php

namespace App\Modules\Fiscal\Application\Contracts;

interface FiscalArtifactStorage
{
    public function put(string $tenantId, string $documentId, string $directory, string $fileName, string $contents): string;

    public function get(string $tenantId, string $storageKey): string;
}
