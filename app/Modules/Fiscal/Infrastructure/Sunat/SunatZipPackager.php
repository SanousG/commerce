<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use RuntimeException;
use ZipArchive;

final class SunatZipPackager
{
    public function package(string $xmlFileName, string $signedXml): string
    {
        if (! preg_match('/^[0-9]{11}-(01-F[A-Z0-9]{3}|03-B[A-Z0-9]{3})-[0-9]{1,8}\.xml$/', $xmlFileName)) {
            throw new RuntimeException('SUNAT_ZIP_ENTRY_NAME_INVALID');
        }

        $path = tempnam(sys_get_temp_dir(), 'sunat-zip-');
        if ($path === false) {
            throw new RuntimeException('SUNAT_ZIP_TEMP_FILE_FAILED');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true
                || ! $zip->addFromString($xmlFileName, $signedXml)
                || ! $zip->setMtimeName($xmlFileName, 0)
                || ! $zip->setArchiveComment('')) {
                throw new RuntimeException('SUNAT_ZIP_PACKAGE_FAILED');
            }
            $zip->close();
            $contents = file_get_contents($path);

            return $contents === false ? throw new RuntimeException('SUNAT_ZIP_READ_FAILED') : $contents;
        } finally {
            @unlink($path);
        }
    }
}
