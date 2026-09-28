<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

enum SunatDeliveryPhase: string
{
    /** The request never reached SUNAT (connection, TLS, WSDL or local setup failure): retry is safe. */
    case PreDelivery = 'PRE_DELIVERY';

    /** SUNAT answered with an application fault: the bill was not processed, retry is safe after fixing the cause. */
    case RemoteResponse = 'REMOTE_RESPONSE';

    /** The outcome is unknown (timeout, malformed payload, dropped connection): re-sending blindly is unsafe. */
    case DeliveryUnknown = 'DELIVERY_UNKNOWN';
}
