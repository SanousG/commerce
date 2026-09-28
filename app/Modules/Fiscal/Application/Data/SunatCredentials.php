<?php

namespace App\Modules\Fiscal\Application\Data;

use App\Modules\Fiscal\Domain\Enums\SunatEnvironment;

final readonly class SunatCredentials
{
    public function __construct(
        public string $username,
        public string $password,
        public SunatEnvironment $environment,
    ) {}
}
