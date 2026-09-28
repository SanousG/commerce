<?php

namespace App\Modules\Tenancy\Domain\Enums;

enum TenantInvitationStatus: string
{
    case Pending = 'PENDING';
    case Accepted = 'ACCEPTED';
    case Expired = 'EXPIRED';
    case Revoked = 'REVOKED';
}
