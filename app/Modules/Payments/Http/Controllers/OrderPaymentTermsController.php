<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Application\ConfigureOrderPaymentTerms;
use App\Modules\Payments\Domain\Enums\PaymentTerms;
use App\Modules\Payments\Http\Requests\UpdateOrderPaymentTermsRequest;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;

class OrderPaymentTermsController extends Controller
{
    public function update(
        UpdateOrderPaymentTermsRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        ConfigureOrderPaymentTerms $action,
        string $order,
    ): RedirectResponse {
        $scoped = $stores->get()->orders()->where('tenant_id', $tenants->id())->findOrFail($order);
        $action->handle($tenants->get(), $stores->get(), $scoped, PaymentTerms::from($request->validated('terms')));

        return back()->with('status', 'Order payment terms updated.');
    }
}
