<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use App\Modules\Fiscal\Application\Contracts\SunatCredentialsProvider;
use App\Modules\Fiscal\Application\Contracts\SunatTransport;
use App\Modules\Fiscal\Application\Data\SunatTransportResult;
use App\Modules\Fiscal\Domain\Enums\SunatEnvironment;
use Illuminate\Contracts\Config\Repository;
use SoapClient;
use SoapFault;
use SoapHeader;
use SoapVar;

final class SunatBetaTransport implements SunatTransport
{
    private const WSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    public function __construct(private SunatCredentialsProvider $credentials, private Repository $config) {}

    public function sendBill(string $tenantId, string $zipFileName, string $zipContents): SunatTransportResult
    {
        $response = $this->call($tenantId, 'sendBill', $zipFileName, $zipContents);
        $cdr = $response->applicationResponse ?? $response->return ?? null;
        if (! is_string($cdr) || $cdr === '') {
            throw new SunatTransportException('SUNAT_MALFORMED_SEND_BILL_RESPONSE', SunatDeliveryPhase::DeliveryUnknown);
        }

        return new SunatTransportResult(cdrZip: $cdr);
    }

    public function sendSummary(string $tenantId, string $zipFileName, string $zipContents): SunatTransportResult
    {
        $response = $this->call($tenantId, 'sendSummary', $zipFileName, $zipContents);
        $ticket = $response->ticket ?? $response->return ?? null;
        if (! is_string($ticket) || trim($ticket) === '') {
            throw new SunatTransportException('SUNAT_MALFORMED_SEND_SUMMARY_RESPONSE', SunatDeliveryPhase::DeliveryUnknown);
        }

        return new SunatTransportResult(ticket: trim($ticket));
    }

    public function getStatus(string $tenantId, string $ticket): SunatTransportResult
    {
        $client = $this->buildClient($tenantId);
        try {
            $response = $client->__soapCall('getStatus', [['ticket' => $ticket]]);
        } catch (SoapFault $exception) {
            throw new SunatTransportException('SUNAT_GET_STATUS_FAILED', $this->classify($exception), $exception, $this->detail($exception));
        } catch (\Throwable $exception) {
            throw new SunatTransportException('SUNAT_GET_STATUS_FAILED', SunatDeliveryPhase::DeliveryUnknown, $exception, SunatFaultClassifier::detail($exception->getMessage()));
        }
        $status = $response->status ?? $response->return ?? $response;
        $code = $status->statusCode ?? null;
        if (! is_string($code) && ! is_int($code)) {
            throw new SunatTransportException('SUNAT_MALFORMED_GET_STATUS_RESPONSE', SunatDeliveryPhase::RemoteResponse);
        }

        return new SunatTransportResult(
            cdrZip: isset($status->content) && is_string($status->content) ? $status->content : null,
            statusCode: (string) $code,
        );
    }

    private function call(string $tenantId, string $operation, string $fileName, string $contents): object
    {
        $client = $this->buildClient($tenantId);

        try {
            return $client->__soapCall($operation, [[
                'fileName' => $fileName,
                'contentFile' => new SoapVar($contents, XSD_BASE64BINARY),
            ]]);
        } catch (SoapFault $exception) {
            throw new SunatTransportException('SUNAT_TRANSPORT_FAILED', $this->classify($exception), $exception, $this->detail($exception));
        } catch (\Throwable $exception) {
            throw new SunatTransportException('SUNAT_TRANSPORT_FAILED', SunatDeliveryPhase::DeliveryUnknown, $exception, SunatFaultClassifier::detail($exception->getMessage()));
        }
    }

    private function buildClient(string $tenantId): SoapClient
    {
        try {
            return $this->client($tenantId);
        } catch (SoapFault $exception) {
            throw new SunatTransportException('SUNAT_TRANSPORT_SETUP_FAILED', $this->classify($exception), $exception, $this->detail($exception));
        } catch (\Throwable $exception) {
            throw new SunatTransportException('SUNAT_TRANSPORT_SETUP_FAILED', SunatDeliveryPhase::PreDelivery, $exception, SunatFaultClassifier::detail($exception->getMessage()));
        }
    }

    private function classify(SoapFault $fault): SunatDeliveryPhase
    {
        return SunatFaultClassifier::classify(
            isset($fault->faultcodens) ? (string) $fault->faultcodens : null,
            ($fault->faultstring ?? '').' '.$fault->getMessage().' '.($fault->faultcode ?? ''),
        );
    }

    private function detail(SoapFault $fault): string
    {
        return SunatFaultClassifier::detail((string) ($fault->faultstring ?? '') !== '' ? (string) $fault->faultstring : $fault->getMessage());
    }

    private function client(string $tenantId): SoapClient
    {
        $credentials = $this->credentials->credentialsFor($tenantId);
        if ($credentials->environment !== SunatEnvironment::Beta) {
            throw new \RuntimeException('SUNAT_BETA_TRANSPORT_REQUIRES_BETA_ENVIRONMENT');
        }
        $client = new SoapClient((string) $this->config->get('sunat.wsdl.BETA'), [
            'exceptions' => true,
            'trace' => false,
            'cache_wsdl' => WSDL_CACHE_MEMORY,
            'connection_timeout' => (int) $this->config->get('sunat.soap.connection_timeout', 10),
        ]);
        $security = sprintf(
            '<wsse:Security xmlns:wsse="%s"><wsse:UsernameToken><wsse:Username>%s</wsse:Username><wsse:Password>%s</wsse:Password></wsse:UsernameToken></wsse:Security>',
            self::WSSE,
            htmlspecialchars($credentials->username, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($credentials->password, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
        );
        $client->__setSoapHeaders(new SoapHeader(self::WSSE, 'Security', new SoapVar($security, XSD_ANYXML), true));

        return $client;
    }
}
