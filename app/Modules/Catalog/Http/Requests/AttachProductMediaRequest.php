<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachProductMediaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'media_asset_id' => ['required', 'uuid'],
            'is_primary' => ['sometimes', 'boolean'],
            'alt_text' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
