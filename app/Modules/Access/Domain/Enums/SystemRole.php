<?php

namespace App\Modules\Access\Domain\Enums;

enum SystemRole: string
{
    case Owner = 'OWNER';
    case Admin = 'ADMIN';
    case Member = 'MEMBER';
}
