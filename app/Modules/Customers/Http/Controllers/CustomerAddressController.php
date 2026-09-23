<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Application\CreateCustomerAddress;
use App\Modules\Customers\Application\CustomerAccess;
use App\Modules\Customers\Application\SetDefaultCustomerAddress;
use App\Modules\Customers\Application\UpdateCustomerAddress;
use App\Modules\Customers\Http\Requests\CustomerAddressRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
    public function store(CustomerAddressRequest $request, TenantContext $context, CreateCustomerAddress $action, string $customer): RedirectResponse
    {
        $tenant = $context->get();
        $scopedCustomer = $tenant->customers()->findOrFail($customer);
        $action->handle($tenant, $scopedCustomer, $request->validated());

        return back()->with('status', 'Address created.');
    }

    public function update(CustomerAddressRequest $request, TenantContext $context, UpdateCustomerAddress $action, string $customer, string $address): RedirectResponse
    {
        $tenant = $context->get();
        $scopedCustomer = $tenant->customers()->findOrFail($customer);
        $scopedAddress = $scopedCustomer->addresses()->findOrFail($address);
        $action->handle($tenant, $scopedCustomer, $scopedAddress, $request->validated());

        return back()->with('status', 'Address updated.');
    }

    public function setDefault(Request $request, TenantContext $context, CustomerAccess $access, SetDefaultCustomerAddress $action, string $customer, string $address, string $type): RedirectResponse
    {
        abort_unless($access->canUpdate($request->user()), 403);
        $tenant = $context->get();
        $scopedCustomer = $tenant->customers()->findOrFail($customer);
        $scopedAddress = $scopedCustomer->addresses()->findOrFail($address);
        $action->handle($tenant, $scopedCustomer, $scopedAddress, $type);

        return back()->with('status', ucfirst($type).' default address updated.');
    }

    public function destroy(Request $request, TenantContext $context, CustomerAccess $access, string $customer, string $address): RedirectResponse
    {
        abort_unless($access->canUpdate($request->user()), 403);
        $scopedCustomer = $context->get()->customers()->findOrFail($customer);
        $scopedCustomer->addresses()->findOrFail($address)->delete();

        return back()->with('status', 'Address deleted.');
    }
}
