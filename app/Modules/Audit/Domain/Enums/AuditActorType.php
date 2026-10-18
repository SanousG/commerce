<?php

namespace App\Modules\Audit\Domain\Enums;

enum AuditActorType: string
{
    case User = 'USER';
    case System = 'SYSTEM';
}
