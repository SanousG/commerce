<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\SaveVariant;
use App\Modules\Catalog\Http\Requests\CreateVariantRequest;
use App\Modules\Catalog\Http\Requests\UpdateVariantRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;

class ProductVariantController extends Controller
{
    public function store(
        CreateVariantRequest $request,
        TenantContext $context,
        SaveVariant $action,
        string $product,
    ): RedirectResponse {
        $scopedProduct = $context->get()->products()->findOrFail($product);
        $action->create($context->get(), $scopedProduct, $request->safe()->only([
            'sku', 'barcode', 'name', 'status', 'is_default', 'option_value_ids',
        ]));

        return back()->with('status', 'Variant created.');
    }

    public function update(
        UpdateVariantRequest $request,
        TenantContext $context,
        SaveVariant $action,
        string $product,
        string $variant,
    ): RedirectResponse {
        $scopedProduct = $context->get()->products()->findOrFail($product);
        $scopedVariant = $scopedProduct->variants()->findOrFail($variant);
        $action->update($context->get(), $scopedProduct, $scopedVariant, $request->safe()->only([
            'sku', 'barcode', 'name', 'status', 'is_default', 'option_value_ids',
        ]));

        return back()->with('status', 'Variant updated.');
    }
}
