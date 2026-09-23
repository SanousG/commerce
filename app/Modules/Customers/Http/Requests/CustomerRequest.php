<?php

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Customers\Application\CustomerAccess;
use Illuminate\Foundation\Http\FormRequest;

abstract class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && match ($this->permission()) {
            PermissionKey::CustomersCreate => app(CustomerAccess::class)->canCreate($user),
            PermissionKey::CustomersUpdate => app(CustomerAccess::class)->canUpdate($user),
            default => false,
        };
    }

    abstract protected function permission(): PermissionKey;
}
