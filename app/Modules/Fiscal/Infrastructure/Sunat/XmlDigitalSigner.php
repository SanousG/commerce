<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use App\Modules\Fiscal\Application\Data\DigitalCertificate;
use DOMDocument;
use DOMXPath;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;

final class XmlDigitalSigner
{
    public function sign(string $contents, DigitalCertificate $certificate): string
    {
        $certificate->assertCurrentlyValid();
        $xml = new DOMDocument;
        if (! $xml->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT)) {
            throw new RuntimeException('SUNAT_SIGNATURE_XML_INVALID');
        }

        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $target = $xpath->query('/*/ext:UBLExtensions/ext:UBLExtension[1]/ext:ExtensionContent')->item(0);
        if (! $target instanceof \DOMElement) {
            $root = $xml->documentElement;
            if ($root === null) {
                throw new RuntimeException('SUNAT_SIGNATURE_TARGET_MISSING');
            }
            $extensions = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2', 'ext:UBLExtensions');
            $extension = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2', 'ext:UBLExtension');
            $target = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2', 'ext:ExtensionContent');
            $extension->appendChild($target);
            $extensions->appendChild($extension);
            $root->insertBefore($extensions, $root->firstChild);
        }

        $signature = new XMLSecurityDSig('ds', ['stripWhitespace' => true]);
        $signature->setSignatureId('SignatureSP');
        $signature->setCanonicalMethod(XMLSecurityDSig::C14N_COMMENTS);
        $signature->addReference(
            $xml,
            XMLSecurityDSig::SHA1,
            [XMLSecurityDSig::ENVELOPED],
            ['force_uri' => true],
        );
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $key->loadKey($certificate->privateKey);
        $signature->add509Cert($certificate->x509, true, false, ['issuerSerial' => true]);
        $signature->sign($key, $target);

        return $xml->saveXML() ?: throw new RuntimeException('SUNAT_SIGNATURE_SERIALIZATION_FAILED');
    }
}
