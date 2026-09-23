<?php

namespace App\Modules\Cart\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use Illuminate\Foundation\Http\FormRequest;

class ManageCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionKey::CartsManage->value) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
