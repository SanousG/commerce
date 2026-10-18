<?php

namespace App\Modules\Pricing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Pricing\Application\ArchivePriceList;
use App\Modules\Pricing\Application\CreatePriceList;
use App\Modules\Pricing\Application\MakeDefaultPriceList;
use App\Modules\Pricing\Application\PricingAccess;
use App\Modules\Pricing\Application\SetVariantPrice;
use App\Modules\Pricing\Application\UpdatePriceList;
use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Pricing\Http\Requests\CreatePriceListRequest;
use App\Modules\Pricing\Http\Requests\SetVariantPriceRequest;
use App\Modules\Pricing\Http\Requests\UpdatePriceListRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PricingController extends Controller
{
    public function index(Request $request, TenantContext $context, PricingAccess $access): Response
    {
        abort_unless($access->canView($request->user()), 403);

        $priceLists = $context->get()->priceLists()
            ->withCount('prices')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (PriceList $priceList): array => $this->priceListData($priceList));

        return Inertia::render('admin/pricing/Index', ['priceLists' => $priceLists]);
    }

    public function create(Request $request, PricingAccess $access): Response
    {
        abort_unless($access->canCreate($request->user()), 403);

        return Inertia::render('admin/pricing/price-lists/Create', [
            'currencies' => config('commerce.currencies', []),
            'defaultCurrency' => config('commerce.default_currency', 'PEN'),
        ]);
    }

    public function store(CreatePriceListRequest $request, TenantContext $context, CreatePriceList $action): RedirectResponse
    {
        $priceList = $action->handle($context->get(), $request->validated());

        return to_route('admin.pricing.price-lists.edit', $priceList)->with('status', 'Price list created.');
    }

    public function edit(
        Request $request,
        TenantContext $context,
        PricingAccess $access,
        string $priceList,
    ): Response {
        abort_unless($access->canView($request->user()), 403);
        $tenant = $context->get();
        $scopedList = $tenant->priceLists()->withCount('prices')->findOrFail($priceList);
        $search = trim((string) $request->query('search'));
        $variants = ProductVariant::query()
            ->where('tenant_id', $tenant->getKey())
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('sku', 'ilike', "%{$search}%")
                    ->orWhereHas('product', fn ($products) => $products->where('name', 'ilike', "%{$search}%"));
            }))
            ->with([
                'product:id,name',
                'prices' => fn ($query) => $query->where('price_list_id', $scopedList->getKey()),
            ])
            ->orderBy('sku')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ProductVariant $variant): array => [
                'id' => $variant->getKey(),
                'product' => $variant->product->name,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'status' => $variant->status->value,
                'amount' => $variant->prices->first()?->amount,
            ]);

        return Inertia::render('admin/pricing/price-lists/Edit', [
            'priceList' => $this->priceListData($scopedList),
            'variants' => $variants,
            'filters' => ['search' => $search],
            'currencies' => config('commerce.currencies', []),
            'statuses' => [PriceListStatus::Active->value, PriceListStatus::Inactive->value],
        ]);
    }

    public function update(
        UpdatePriceListRequest $request,
        TenantContext $context,
        UpdatePriceList $action,
        string $priceList,
    ): RedirectResponse {
        $scopedList = $context->get()->priceLists()->findOrFail($priceList);
        $action->handle($context->get(), $scopedList, $request->validated());

        return back()->with('status', 'Price list updated.');
    }

    public function makeDefault(
        Request $request,
        TenantContext $context,
        PricingAccess $access,
        MakeDefaultPriceList $action,
        string $priceList,
    ): RedirectResponse {
        abort_unless($access->canUpdate($request->user()), 403);
        $scopedList = $context->get()->priceLists()->findOrFail($priceList);
        $action->handle($context->get(), $scopedList);

        return back()->with('status', 'Default price list changed.');
    }

    public function archive(
        Request $request,
        TenantContext $context,
        PricingAccess $access,
        ArchivePriceList $action,
        string $priceList,
    ): RedirectResponse {
        abort_unless($access->canArchive($request->user()), 403);
        $scopedList = $context->get()->priceLists()->findOrFail($priceList);
        $action->handle($context->get(), $scopedList);

        return to_route('admin.pricing.index')->with('status', 'Price list archived.');
    }

    public function setVariantPrice(
        SetVariantPriceRequest $request,
        TenantContext $context,
        SetVariantPrice $action,
        string $priceList,
        string $variant,
    ): RedirectResponse {
        $tenant = $context->get();
        $scopedList = $tenant->priceLists()->findOrFail($priceList);
        $scopedVariant = ProductVariant::query()->where('tenant_id', $tenant->getKey())->findOrFail($variant);
        $action->handle($tenant, $scopedList, $scopedVariant, $request->validated('amount'), $request->user());

        return back()->with('status', 'Price saved.');
    }

    public function removeVariantPrice(
        Request $request,
        TenantContext $context,
        PricingAccess $access,
        SetVariantPrice $action,
        string $priceList,
        string $variant,
    ): RedirectResponse {
        abort_unless($access->canUpdate($request->user()), 403);
        $tenant = $context->get();
        $scopedList = $tenant->priceLists()->findOrFail($priceList);
        $scopedVariant = ProductVariant::query()->where('tenant_id', $tenant->getKey())->findOrFail($variant);
        $action->remove($tenant, $scopedList, $scopedVariant);

        return back()->with('status', 'Price removed.');
    }

    private function priceListData(PriceList $priceList): array
    {
        return [
            'id' => $priceList->getKey(),
            'name' => $priceList->name,
            'code' => $priceList->code,
            'currency' => $priceList->currency,
            'status' => $priceList->status->value,
            'is_default' => $priceList->is_default,
            'prices_count' => $priceList->prices_count,
        ];
    }
}
