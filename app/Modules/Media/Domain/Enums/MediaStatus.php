<?php

namespace App\Modules\Media\Domain\Enums;

enum MediaStatus: string
{
    case Ready = 'READY';
    case Deleting = 'DELETING';
}
