<?php

namespace App\Modules\Listings\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;

class UpdateStoreListingSettingsRequest extends ListingRequest
{
    public function rules(): array
    {
        return ['default_price_list_id' => ['nullable', 'uuid']];
    }

    protected function permission(): PermissionKey
    {
        return PermissionKey::ListingsUpdate;
    }
}
