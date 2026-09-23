<?php

namespace App\Modules\Listings\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Listings\Application\ListingAccess;
use Illuminate\Foundation\Http\FormRequest;

abstract class ListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return match ($this->permission()) {
            PermissionKey::ListingsCreate => app(ListingAccess::class)->canCreate($user),
            PermissionKey::ListingsUpdate => app(ListingAccess::class)->canUpdate($user),
            default => false,
        };
    }

    abstract protected function permission(): PermissionKey;
}
