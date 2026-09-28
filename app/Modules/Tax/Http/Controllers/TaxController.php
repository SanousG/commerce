<?php

namespace App\Modules\Tax\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tax\Application\SaveTaxCategory;
use App\Modules\Tax\Application\SaveTaxRate;
use App\Modules\Tax\Domain\Enums\TaxCategoryCode;
use App\Modules\Tax\Domain\Enums\TaxCategoryStatus;
use App\Modules\Tax\Domain\Models\TaxCategory;
use App\Modules\Tax\Domain\Models\TaxRate;
use App\Modules\Tax\Http\Requests\TaxCategoryRequest;
use App\Modules\Tax\Http\Requests\TaxRateRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller
{
    public function index(TenantContext $tenants): Response
    {
        $categories = $tenants->get()->taxCategories()->orderBy('name')->get()
            ->map(fn (TaxCategory $category): array => [
                'id' => $category->getKey(),
                'code' => $category->code->value,
                'name' => $category->name,
                'status' => $category->status->value,
            ])->all();
        $rates = $tenants->get()->taxRates()->orderBy('name')->orderBy('valid_from')->get()
            ->map(fn (TaxRate $rate): array => [
                'id' => $rate->getKey(),
                'tax_category_id' => $rate->tax_category_id,
                'name' => $rate->name,
                'rate' => $rate->rate,
                'valid_from' => $rate->valid_from?->toDateString(),
                'valid_until' => $rate->valid_until?->toDateString(),
                'status' => $rate->status->value,
            ])->all();

        return Inertia::render('admin/taxes/Index', [
            'categories' => $categories,
            'rates' => $rates,
            'categoryCodes' => array_column(TaxCategoryCode::cases(), 'value'),
            'statuses' => array_column(TaxCategoryStatus::cases(), 'value'),
        ]);
    }

    public function storeCategory(
        TaxCategoryRequest $request,
        TenantContext $tenants,
        SaveTaxCategory $action,
    ): RedirectResponse {
        $action->create($tenants->get(), $request->validated());

        return back()->with('status', 'Tax category created.');
    }

    public function updateCategory(
        TaxCategoryRequest $request,
        TenantContext $tenants,
        SaveTaxCategory $action,
        string $taxCategory,
    ): RedirectResponse {
        $category = $tenants->get()->taxCategories()->findOrFail($taxCategory);
        $action->update($category, $request->validated());

        return back()->with('status', 'Tax category updated.');
    }

    public function storeRate(
        TaxRateRequest $request,
        TenantContext $tenants,
        SaveTaxRate $action,
    ): RedirectResponse {
        $action->create($tenants->get(), $request->validated());

        return back()->with('status', 'Tax rate created.');
    }

    public function updateRate(
        TaxRateRequest $request,
        TenantContext $tenants,
        SaveTaxRate $action,
        string $taxRate,
    ): RedirectResponse {
        $rate = $tenants->get()->taxRates()->findOrFail($taxRate);
        $action->update($rate, $request->validated());

        return back()->with('status', 'Tax rate updated.');
    }
}
