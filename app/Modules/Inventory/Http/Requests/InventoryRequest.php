<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Application\InventoryAccess;
use Illuminate\Foundation\Http\FormRequest;

abstract class InventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(InventoryAccess::class)->canAdjust($user);
    }
}
