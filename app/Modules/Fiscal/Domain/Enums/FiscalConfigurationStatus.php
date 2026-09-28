<?php

namespace App\Modules\Fiscal\Domain\Enums;

enum FiscalConfigurationStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
}
