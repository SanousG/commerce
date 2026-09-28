<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use App\Modules\Fiscal\Application\Data\DigitalCertificate;
use DOMDocument;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

final class XmlSignatureVerifier
{
    public function verify(string $contents, DigitalCertificate $certificate): bool
    {
        $xml = new DOMDocument;
        if (! $xml->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT)) {
            return false;
        }

        try {
            $signature = new XMLSecurityDSig;
            $signatureNode = $signature->locateSignature($xml);
            if ($signatureNode === null) {
                return false;
            }
            $signature->canonicalizeSignedInfo();
            if (! $signature->validateReference()) {
                return false;
            }
            $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'public']);
            $key->loadKey($certificate->x509, false, true);

            $result = $signature->verify($key);
            if ($result !== 1) {
                return false;
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
