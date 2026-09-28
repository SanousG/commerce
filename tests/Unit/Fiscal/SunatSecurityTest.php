<?php

namespace Tests\Unit\Fiscal;

use App\Modules\Fiscal\Application\Data\DigitalCertificate;
use App\Modules\Fiscal\Infrastructure\Sunat\ConfigSunatCredentialsProvider;
use App\Modules\Fiscal\Infrastructure\Sunat\SunatCdrParser;
use App\Modules\Fiscal\Infrastructure\Sunat\SunatZipPackager;
use App\Modules\Fiscal\Infrastructure\Sunat\XmlDigitalSigner;
use App\Modules\Fiscal\Infrastructure\Sunat\XmlSignatureVerifier;
use Carbon\CarbonImmutable;
use Tests\TestCase;
use ZipArchive;

final class SunatSecurityTest extends TestCase
{
    public function test_signature_verifies_and_fails_after_xml_mutation(): void
    {
        $certificate = $this->certificate();
        $unsigned = '<?xml version="1.0" encoding="UTF-8"?><Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"><ext:UBLExtensions><ext:UBLExtension><ext:ExtensionContent/></ext:UBLExtension></ext:UBLExtensions><ID>TEST</ID></Invoice>';
        $signed = (new XmlDigitalSigner)->sign($unsigned, $certificate);
        $verifier = new XmlSignatureVerifier;

        $this->assertTrue($verifier->verify($signed, $certificate));
        $this->assertFalse($verifier->verify(str_replace('TEST', 'ALTERED', $signed), $certificate));
    }

    public function test_signing_is_deterministic_for_documents_without_comments(): void
    {
        $certificate = $this->certificate();
        $unsigned = '<?xml version="1.0" encoding="UTF-8"?><Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"><ext:UBLExtensions><ext:UBLExtension><ext:ExtensionContent/></ext:UBLExtension></ext:UBLExtensions><ID>DETERMINISM</ID></Invoice>';
        $signer = new XmlDigitalSigner;
        $first = $signer->sign($unsigned, $certificate);
        $second = $signer->sign($unsigned, $certificate);

        $this->assertSame($first, $second);
        $this->assertStringNotContainsString('<!--', $first);
        $this->assertTrue((new XmlSignatureVerifier)->verify($first, $certificate));
    }

    public function test_zip_contains_exactly_the_named_xml_and_is_byte_deterministic(): void
    {
        $contents = (new SunatZipPackager)->package('20123456789-01-F001-1.xml', '<Invoice/>');
        $again = (new SunatZipPackager)->package('20123456789-01-F001-1.xml', '<Invoice/>');
        $path = tempnam(sys_get_temp_dir(), 'sunat-test-');
        file_put_contents($path, $contents);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertSame(1, $zip->numFiles);
        $this->assertSame('20123456789-01-F001-1.xml', $zip->getNameIndex(0));
        $this->assertSame('<Invoice/>', $zip->getFromIndex(0));
        $zip->close();
        unlink($path);

        $this->assertSame($contents, $again);
        $this->assertSame(hash('sha256', $contents), hash('sha256', $again));
    }

    public function test_cdr_parser_preserves_acceptance_observations_and_reference(): void
    {
        $xml = '<?xml version="1.0"?><ApplicationResponse xmlns="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"><cbc:Note>Observación de prueba</cbc:Note><cac:DocumentResponse><cac:Response><cbc:ReferenceID>F001-1</cbc:ReferenceID><cbc:ResponseCode>0</cbc:ResponseCode><cbc:Description>Aceptado</cbc:Description></cac:Response></cac:DocumentResponse></ApplicationResponse>';
        $result = (new SunatCdrParser)->parseXml($xml);

        $this->assertTrue($result->accepted());
        $this->assertSame('F001-1', $result->documentReference);
        $this->assertSame(['Observación de prueba'], $result->observations);
    }

    public function test_production_environment_is_blocked_without_explicit_flag(): void
    {
        config()->set('sunat.environment', 'PRODUCTION');
        config()->set('sunat.production_enabled', false);
        config()->set('sunat.credentials.username', 'configured-user');
        config()->set('sunat.credentials.password', 'configured-secret');

        $this->expectExceptionMessage('SUNAT_PRODUCTION_DISABLED');
        app(ConfigSunatCredentialsProvider::class)->credentialsFor('tenant-fixture');
    }

    private function certificate(): DigitalCertificate
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'SUNAT fixture'], $key, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
        openssl_x509_export($x509, $certificate);
        openssl_pkey_export($key, $privateKey);

        return new DigitalCertificate(
            $certificate,
            $privateKey,
            CarbonImmutable::now()->subMinute(),
            CarbonImmutable::now()->addDay(),
            strtolower((string) openssl_x509_fingerprint($certificate, 'sha256')),
        );
    }
}
