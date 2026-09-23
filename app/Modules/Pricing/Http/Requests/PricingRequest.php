<?php

namespace App\Modules\Pricing\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Pricing\Application\PricingAccess;
use Illuminate\Foundation\Http\FormRequest;

abstract class PricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && match ($this->permission()) {
            PermissionKey::PricingCreate => app(PricingAccess::class)->canCreate($user),
            PermissionKey::PricingUpdate => app(PricingAccess::class)->canUpdate($user),
            default => false,
        };
    }

    abstract protected function permission(): PermissionKey;
}
