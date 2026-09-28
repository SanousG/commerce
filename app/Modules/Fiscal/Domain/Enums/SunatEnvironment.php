<?php

namespace App\Modules\Fiscal\Domain\Enums;

enum SunatEnvironment: string
{
    case Beta = 'BETA';
    case Production = 'PRODUCTION';
}
