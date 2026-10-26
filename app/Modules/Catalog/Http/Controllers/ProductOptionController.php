<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\CreateProductOption;
use App\Modules\Catalog\Http\Requests\CreateProductOptionRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;

class ProductOptionController extends Controller
{
    public function store(
        CreateProductOptionRequest $request,
        TenantContext $context,
        CreateProductOption $action,
        string $product,
    ): RedirectResponse {
        $scopedProduct = $context->get()->products()->findOrFail($product);
        $action->handle($context->get(), $scopedProduct, $request->safe()->only(['name', 'position', 'values']));

        return back()->with('status', __('Product option created.'));
    }
}
