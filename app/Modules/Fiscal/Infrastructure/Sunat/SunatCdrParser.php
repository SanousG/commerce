<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use App\Modules\Fiscal\Application\Data\SunatCdrResult;
use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

final class SunatCdrParser
{
    public function parseZip(string $contents): SunatCdrResult
    {
        return $this->parseXml($this->extractXml($contents));
    }

    public function extractXml(string $contents): string
    {
        if (strlen($contents) > (int) config('sunat.max_cdr_bytes')) {
            throw new RuntimeException('SUNAT_CDR_TOO_LARGE');
        }
        $path = tempnam(sys_get_temp_dir(), 'sunat-cdr-');
        if ($path === false || file_put_contents($path, $contents) === false) {
            throw new RuntimeException('SUNAT_CDR_TEMP_FILE_FAILED');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true || $zip->numFiles !== 1) {
                throw new RuntimeException('SUNAT_CDR_ZIP_INVALID');
            }
            $name = $zip->getNameIndex(0);
            if ($name === false || ! preg_match('/^R-[A-Za-z0-9-]+\.xml$/', $name) || basename($name) !== $name) {
                throw new RuntimeException('SUNAT_CDR_ENTRY_INVALID');
            }
            $xml = $zip->getFromIndex(0, (int) config('sunat.max_cdr_bytes'));
            $zip->close();
            if ($xml === false) {
                throw new RuntimeException('SUNAT_CDR_XML_MISSING');
            }

            return $xml;
        } finally {
            @unlink($path);
        }
    }

    public function parseXml(string $contents): SunatCdrResult
    {
        $xml = new DOMDocument;
        if (! $xml->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT)) {
            throw new RuntimeException('SUNAT_CDR_XML_INVALID');
        }
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $response = '/ar:ApplicationResponse/cac:DocumentResponse/cac:Response';
        $xpath->registerNamespace('ar', (string) $xml->documentElement?->namespaceURI);
        $code = trim((string) $xpath->evaluate("string({$response}/cbc:ResponseCode)"));
        $description = trim((string) $xpath->evaluate("string({$response}/cbc:Description)"));
        $reference = trim((string) $xpath->evaluate("string({$response}/cbc:ReferenceID)"));
        if ($reference === '') {
            $reference = trim((string) $xpath->evaluate('string(/ar:ApplicationResponse/cac:DocumentResponse/cac:DocumentReference/cbc:ID)'));
        }
        if ($code === '' || $description === '' || $reference === '') {
            throw new RuntimeException('SUNAT_CDR_REQUIRED_FIELDS_MISSING');
        }
        $observations = [];
        foreach ($xpath->query('/ar:ApplicationResponse/cbc:Note') ?: [] as $note) {
            $value = trim($note->textContent);
            if ($value !== '') {
                $observations[] = $value;
            }
        }

        return new SunatCdrResult($code, $description, $observations, $reference);
    }
}
