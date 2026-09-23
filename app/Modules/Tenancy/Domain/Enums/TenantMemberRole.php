<?php

namespace App\Modules\Tenancy\Domain\Enums;

/**
 * Legacy values retained only so the historical tenant_members migration remains replayable.
 */
enum TenantMemberRole: string
{
    case Owner = 'OWNER';
    case Admin = 'ADMIN';
    case Member = 'MEMBER';
}
