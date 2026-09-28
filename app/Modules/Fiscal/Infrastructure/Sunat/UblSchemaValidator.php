<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use App\Modules\Fiscal\Application\Data\XmlValidationResult;
use DOMDocument;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\FilesystemManager;
use LibXMLError;
use RuntimeException;

final class UblSchemaValidator
{
    public function __construct(private FilesystemManager $filesystems, private Repository $config) {}

    public function validateInvoice(string $contents): XmlValidationResult
    {
        $disk = $this->filesystems->disk((string) $this->config->get('sunat.storage_disk'));
        $schema = $disk->path((string) $this->config->get('sunat.xsd_invoice'));
        if (! is_file($schema)) {
            throw new RuntimeException('SUNAT_XSD_NOT_PROVISIONED');
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $xml = new DOMDocument;
            $loaded = $xml->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
            $valid = $loaded && $xml->schemaValidate($schema);
            $errors = array_map(static fn (LibXMLError $error): array => [
                'line' => $error->line,
                'column' => $error->column,
                'code' => $error->code,
                'message' => trim($error->message),
            ], libxml_get_errors());

            return new XmlValidationResult($valid, $errors);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
