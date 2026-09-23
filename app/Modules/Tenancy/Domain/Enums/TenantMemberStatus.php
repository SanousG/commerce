<?php

namespace App\Modules\Tenancy\Domain\Enums;

enum TenantMemberStatus: string
{
    case Active = 'ACTIVE';
    case Invited = 'INVITED';
    case Suspended = 'SUSPENDED';
    case Removed = 'REMOVED';
}
