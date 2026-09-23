<?php

namespace App\Modules\Tenancy\Domain\Enums;

enum TenantStatus: string
{
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Archived = 'ARCHIVED';
}
