<?php

namespace App\Modules\Fiscal\Application\Data;

final readonly class SunatTransportResult
{
    public function __construct(
        public ?string $cdrZip = null,
        public ?string $ticket = null,
        public ?string $statusCode = null,
    ) {}
}
