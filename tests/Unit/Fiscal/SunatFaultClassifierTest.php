<?php

namespace Tests\Unit\Fiscal;

use App\Modules\Fiscal\Infrastructure\Sunat\SunatDeliveryPhase;
use App\Modules\Fiscal\Infrastructure\Sunat\SunatFaultClassifier;
use Tests\TestCase;

final class SunatFaultClassifierTest extends TestCase
{
    public function test_server_faults_are_classified_as_remote_responses(): void
    {
        $phase = SunatFaultClassifier::classify('urn:pe:gob:sunat:cpe:see:gem:establecimientos:cpeErrorBusiness-1.1', '1033 - Cuenta sin acceso');

        $this->assertSame(SunatDeliveryPhase::RemoteResponse, $phase);
    }

    public function test_connection_and_wsdl_failures_are_classified_as_pre_delivery(): void
    {
        $samples = [
            'Could not connect to host e-beta.sunat.gob.pe',
            'SOAP-ERROR: Parsing WSDL: Couldn\'t load from \'https://e-beta.sunat.gob.pe\' : failed to load external entity',
            'Connection refused by 200.1.113.10:443',
            'fsockopen(): unable to connect to e-beta.sunat.gob.pe:443 (Connection timed out)',
            'php_network_getaddresses: getaddrinfo failed: Name or service not known',
            'certificate verify failed: self signed certificate',
            'SSL handshake failed',
        ];

        foreach ($samples as $sample) {
            $this->assertSame(
                SunatDeliveryPhase::PreDelivery,
                SunatFaultClassifier::classify(null, $sample),
                "Expected pre-delivery classification for: {$sample}",
            );
        }
    }

    public function test_ambiguous_failures_are_classified_as_delivery_unknown(): void
    {
        $samples = [
            'Operation timed out after 10000 milliseconds with 0 bytes received',
            'Internal Server Error',
            'Error Fetching http headers',
            'Connection reset by peer',
            'sunat-bad-response',
        ];

        foreach ($samples as $sample) {
            $this->assertSame(
                SunatDeliveryPhase::DeliveryUnknown,
                SunatFaultClassifier::classify(null, $sample),
                "Expected delivery-unknown classification for: {$sample}",
            );
        }
    }

    public function test_detail_is_collapsed_and_truncated(): void
    {
        $detail = SunatFaultClassifier::detail(str_repeat("fault\nstring  ", 100));

        $this->assertLessThanOrEqual(400, mb_strlen($detail));
        $this->assertStringNotContainsString("\n", $detail);
        $this->assertStringContainsString('fault string', $detail);
    }
}
