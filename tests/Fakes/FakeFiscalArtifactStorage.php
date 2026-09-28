<?php

namespace Tests\Fakes;

use App\Modules\Fiscal\Application\Contracts\FiscalArtifactStorage;
use RuntimeException;

final class FakeFiscalArtifactStorage implements FiscalArtifactStorage
{
    /** @var array<string, array<string, string>> tenant id => storage key => contents */
    public array $files = [];

    public function put(string $tenantId, string $documentId, string $directory, string $fileName, string $contents): string
    {
        $key = "tenants/{$tenantId}/documents/{$documentId}/{$directory}/{$fileName}";
        $this->files[$tenantId][$key] = $contents;

        return $key;
    }

    public function get(string $tenantId, string $storageKey): string
    {
        return $this->files[$tenantId][$storageKey] ?? throw new RuntimeException('FAKE_ARTIFACT_NOT_FOUND');
    }
}
