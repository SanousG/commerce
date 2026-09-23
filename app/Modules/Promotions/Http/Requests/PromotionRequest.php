<?php

namespace App\Modules\Promotions\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Promotions\Application\PromotionAccess;
use Illuminate\Foundation\Http\FormRequest;

abstract class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && match ($this->permission()) {
            PermissionKey::PromotionsCreate => app(PromotionAccess::class)->canCreate($user),
            PermissionKey::PromotionsUpdate => app(PromotionAccess::class)->canUpdate($user),
            default => false,
        };
    }

    abstract protected function permission(): PermissionKey;
}
