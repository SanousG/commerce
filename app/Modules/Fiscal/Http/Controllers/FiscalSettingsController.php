<?php

namespace App\Modules\Fiscal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fiscal\Application\UpdateFiscalIssuerProfile;
use App\Modules\Fiscal\Domain\Enums\FiscalConfigurationStatus;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentType;
use App\Modules\Fiscal\Domain\Models\FiscalIssuerProfile;
use App\Modules\Fiscal\Domain\Models\FiscalSeries;
use App\Modules\Fiscal\Http\Requests\FiscalIssuerProfileRequest;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FiscalSettingsController extends Controller
{
    public function show(Request $request, TenantContext $tenants): Response
    {
        $tenant = $tenants->get();
        $profile = $tenant->fiscalIssuerProfile()->first();
        $store = $tenant->stores()
            ->whereKey($request->session()->get(StoreContext::SESSION_KEY))
            ->where('status', StoreStatus::Active->value)
            ->first();
        $series = $store?->fiscalSeries()->where('tenant_id', $tenant->getKey())
            ->orderBy('document_type')->orderBy('series')->get() ?? collect();

        return Inertia::render('admin/fiscal/Settings', [
            'issuerProfile' => $profile === null ? null : $this->serializeProfile($profile),
            'store' => $store === null ? null : ['id' => $store->getKey(), 'name' => $store->name],
            'series' => $series->map(fn (FiscalSeries $item): array => $this->serializeSeries($item))->all(),
            'documentTypes' => array_column(FiscalDocumentType::cases(), 'value'),
            'statuses' => array_column(FiscalConfigurationStatus::cases(), 'value'),
        ]);
    }

    public function update(
        FiscalIssuerProfileRequest $request,
        TenantContext $tenants,
        UpdateFiscalIssuerProfile $action,
    ): RedirectResponse {
        $action->handle($tenants->get(), $request->validated());

        return back()->with('status', 'Fiscal issuer profile updated.');
    }

    private function serializeProfile(FiscalIssuerProfile $profile): array
    {
        return [
            'id' => $profile->getKey(),
            'country_code' => $profile->country_code,
            'tax_id' => $profile->tax_id,
            'legal_name' => $profile->legal_name,
            'trade_name' => $profile->trade_name,
            'fiscal_address_line_1' => $profile->fiscal_address_line_1,
            'fiscal_address_line_2' => $profile->fiscal_address_line_2,
            'district' => $profile->district,
            'province' => $profile->province,
            'region' => $profile->region,
            'postal_code' => $profile->postal_code,
            'ubigeo' => $profile->ubigeo,
            'status' => $profile->status->value,
        ];
    }

    private function serializeSeries(FiscalSeries $series): array
    {
        return [
            'id' => $series->getKey(),
            'document_type' => $series->document_type->value,
            'series' => $series->series,
            'number_padding' => $series->number_padding,
            'next_number' => $series->next_number,
            'status' => $series->status->value,
        ];
    }
}
