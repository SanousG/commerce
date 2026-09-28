<?php

namespace App\Modules\Fiscal\Application\Data;

final readonly class SunatCdrResult
{
    /** @param list<string> $observations */
    public function __construct(
        public string $responseCode,
        public string $description,
        public array $observations,
        public string $documentReference,
    ) {}

    public function accepted(): bool
    {
        return $this->responseCode === '0';
    }
}
