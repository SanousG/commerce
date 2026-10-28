<?php

namespace App\Modules\Stores\Domain\Enums;

enum StorefrontPublicationStatus: string
{
    case Unpublished = 'UNPUBLISHED';
    case Published = 'PUBLISHED';
}
