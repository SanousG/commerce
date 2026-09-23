<?php

namespace App\Modules\Listings\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;

class CreateListingRequest extends ListingRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['storefront_slug' => filled($this->input('storefront_slug')) ? trim((string) $this->input('storefront_slug')) : null]);
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid'],
            'storefront_slug' => ['nullable', 'string', 'max:180'],
            'price_list_id' => ['nullable', 'uuid'],
        ];
    }

    protected function permission(): PermissionKey
    {
        return PermissionKey::ListingsCreate;
    }
}
