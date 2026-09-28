<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use App\Modules\Fiscal\Application\Contracts\FiscalArtifactStorage;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use InvalidArgumentException;

final class PrivateFiscalArtifactStorage implements FiscalArtifactStorage
{
    public function __construct(private FilesystemManager $filesystems, private Repository $config) {}

    public function put(string $tenantId, string $documentId, string $directory, string $fileName, string $contents): string
    {
        foreach ([$tenantId, $documentId, $directory, $fileName] as $part) {
            if ($part === '' || str_contains($part, '..') || str_contains($part, '/') || str_contains($part, '\\')) {
                throw new InvalidArgumentException('Invalid fiscal artifact path component.');
            }
        }

        $key = "tenants/{$tenantId}/documents/{$documentId}/{$directory}/{$fileName}";
        $this->disk()->put($key, $contents);

        return $key;
    }

    public function get(string $tenantId, string $storageKey): string
    {
        $prefix = "tenants/{$tenantId}/";
        if (! str_starts_with($storageKey, $prefix) || str_contains($storageKey, '..')) {
            throw new InvalidArgumentException('Fiscal artifact is outside the tenant boundary.');
        }

        return $this->disk()->get($storageKey);
    }

    private function disk(): Filesystem
    {
        return $this->filesystems->disk((string) $this->config->get('sunat.storage_disk'));
    }
}
