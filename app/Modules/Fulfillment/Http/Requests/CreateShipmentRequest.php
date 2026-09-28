<?php

namespace App\Modules\Fulfillment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('shipments.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'carrier_name' => ['nullable', 'string', 'max:120'],
            'service_name' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:160'],
            'tracking_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
