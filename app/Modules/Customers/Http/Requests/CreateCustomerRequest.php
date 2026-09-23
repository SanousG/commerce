<?php

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;

class CreateCustomerRequest extends SaveCustomerRequest
{
    protected function permission(): PermissionKey
    {
        return PermissionKey::CustomersCreate;
    }
}
