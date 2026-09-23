<?php

namespace App\Modules\Listings\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;

class UpdateListingRequest extends ListingRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['storefront_slug' => trim((string) $this->input('storefront_slug'))]);
    }

    public function rules(): array
    {
        return [
            'storefront_slug' => ['required', 'string', 'max:180'],
            'price_list_id' => ['nullable', 'uuid'],
            'enabled_variant_ids' => ['present', 'array'],
            'enabled_variant_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    protected function permission(): PermissionKey
    {
        return PermissionKey::ListingsUpdate;
    }
}
