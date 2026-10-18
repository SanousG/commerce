<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachVariantMediaRequest extends FormRequest
{
    public function rules(): array { return ['media_asset_id' => ['required', 'uuid']]; }
}
