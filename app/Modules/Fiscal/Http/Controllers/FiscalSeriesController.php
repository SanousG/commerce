<?php

namespace App\Modules\Fiscal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fiscal\Application\SaveFiscalSeries;
use App\Modules\Fiscal\Http\Requests\FiscalSeriesRequest;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;

class FiscalSeriesController extends Controller
{
    public function store(
        FiscalSeriesRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        SaveFiscalSeries $action,
    ): RedirectResponse {
        $action->create($tenants->get(), $stores->get(), $request->validated());

        return back()->with('status', 'Fiscal series created.');
    }

    public function update(
        FiscalSeriesRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        SaveFiscalSeries $action,
        string $fiscalSeries,
    ): RedirectResponse {
        $series = $tenants->get()->fiscalSeries()
            ->where('store_id', $stores->id())
            ->findOrFail($fiscalSeries);
        $action->update($tenants->get(), $stores->get(), $series, $request->validated());

        return back()->with('status', 'Fiscal series updated.');
    }
}
