<?php

namespace App\Modules\Fiscal\Application;

final readonly class FiscalPayload
{
    public function __construct(
        public string $contents,
        public string $mediaType,
        public string $sha256,
        public string $fileName = '',
    ) {}
}
