<?php

namespace App\Modules\Promotions\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;

class CreatePromotionRequest extends SavePromotionRequest
{
    protected function permission(): PermissionKey
    {
        return PermissionKey::PromotionsCreate;
    }
}
