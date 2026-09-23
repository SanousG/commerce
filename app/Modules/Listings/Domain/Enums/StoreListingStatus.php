<?php

namespace App\Modules\Listings\Domain\Enums;

enum StoreListingStatus: string
{
    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
    case Unpublished = 'UNPUBLISHED';
    case Archived = 'ARCHIVED';
}
