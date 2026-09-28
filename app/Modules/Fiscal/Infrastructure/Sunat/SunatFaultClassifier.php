<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

final class SunatFaultClassifier
{
    private const PRE_DELIVERY_PATTERN = '/wsdl|could not connect|connection refused|connection timed out|no route to host|network is unreachable|host is unreachable|php_network_getaddresses|getaddrinfo|name or service not known|temporary failure in name resolution|failed to load|unable to load|failed to open stream|certificate verify failed|ssl handshake|ssl connection/i';

    private const MAX_DETAIL_LENGTH = 400;

    public static function classify(?string $faultCodeNamespace, string $text): SunatDeliveryPhase
    {
        if (trim((string) $faultCodeNamespace) !== '') {
            return SunatDeliveryPhase::RemoteResponse;
        }

        if (preg_match(self::PRE_DELIVERY_PATTERN, $text) === 1) {
            return SunatDeliveryPhase::PreDelivery;
        }

        return SunatDeliveryPhase::DeliveryUnknown;
    }

    public static function detail(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        return mb_substr($text, 0, self::MAX_DETAIL_LENGTH);
    }
}
