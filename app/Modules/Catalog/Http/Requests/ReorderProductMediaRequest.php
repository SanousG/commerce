<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderProductMediaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'media_asset_ids' => ['required', 'array'],
            'media_asset_ids.*' => ['required', 'uuid', 'distinct:strict'],
            'primary_media_asset_id' => ['nullable', 'uuid'],
        ];
    }
}
