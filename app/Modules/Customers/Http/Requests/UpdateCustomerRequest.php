<?php

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Tenancy\Application\TenantContext;

class UpdateCustomerRequest extends SaveCustomerRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        app(TenantContext::class)->get()->customers()->findOrFail((string) $this->route('customer'));

        return true;
    }

    protected function permission(): PermissionKey
    {
        return PermissionKey::CustomersUpdate;
    }
}
