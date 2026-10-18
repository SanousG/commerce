<?php

namespace App\Modules\Media\Domain\Enums;

enum MediaType: string
{
    case Image = 'IMAGE';
    case Document = 'DOCUMENT';
}
