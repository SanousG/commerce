<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderVariantMediaRequest extends FormRequest
{
    public function rules(): array
    {
        return ['media_asset_ids' => ['required', 'array'], 'media_asset_ids.*' => ['required', 'uuid', 'distinct:strict']];
    }
}
