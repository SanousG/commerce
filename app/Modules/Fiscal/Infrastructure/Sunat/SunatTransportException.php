<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use RuntimeException;

final class SunatTransportException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly SunatDeliveryPhase $phase,
        ?\Throwable $previous = null,
        public readonly ?string $detail = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
