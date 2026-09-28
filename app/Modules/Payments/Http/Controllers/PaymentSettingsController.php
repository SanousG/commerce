<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Application\UpdateStorePaymentSettings;
use App\Modules\Payments\Domain\Enums\CodDepositType;
use App\Modules\Payments\Domain\Models\StorePaymentSettings;
use App\Modules\Payments\Http\Requests\UpdatePaymentSettingsRequest;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentSettingsController extends Controller
{
    public function show(TenantContext $tenants, StoreContext $stores): Response
    {
        $settings = StorePaymentSettings::query()->where('tenant_id', $tenants->id())
            ->where('store_id', $stores->id())->first();

        return Inertia::render('admin/payments/Settings', ['settings' => $settings === null ? [
            'bank_transfer_enabled' => false, 'cash_enabled' => false, 'online_enabled' => false,
            'cod_enabled' => false, 'cod_deposit_type' => CodDepositType::None->value,
            'cod_deposit_value' => '0.0000',
        ] : $this->serialize($settings)]);
    }

    public function update(
        UpdatePaymentSettingsRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        UpdateStorePaymentSettings $action,
    ): RedirectResponse {
        $action->handle($tenants->get(), $stores->get(), $request->validated());

        return back()->with('status', 'Payment settings updated.');
    }

    private function serialize(StorePaymentSettings $settings): array
    {
        return [
            'bank_transfer_enabled' => $settings->bank_transfer_enabled,
            'cash_enabled' => $settings->cash_enabled, 'online_enabled' => $settings->online_enabled,
            'cod_enabled' => $settings->cod_enabled, 'cod_deposit_type' => $settings->cod_deposit_type->value,
            'cod_deposit_value' => $settings->cod_deposit_value,
        ];
    }
}
