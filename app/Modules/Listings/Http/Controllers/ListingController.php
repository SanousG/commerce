<?php

namespace App\Modules\Listings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Listings\Application\ArchiveListing;
use App\Modules\Listings\Application\CreateListing;
use App\Modules\Listings\Application\ListingAccess;
use App\Modules\Listings\Application\ListingPriceResolver;
use App\Modules\Listings\Application\PublishListing;
use App\Modules\Listings\Application\SetStoreDefaultPriceList;
use App\Modules\Listings\Application\UnpublishListing;
use App\Modules\Listings\Application\UpdateListing;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Listings\Http\Requests\CreateListingRequest;
use App\Modules\Listings\Http\Requests\UpdateListingRequest;
use App\Modules\Listings\Http\Requests\UpdateStoreListingSettingsRequest;
use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Promotions\Application\EffectivePriceCalculator;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListingController extends Controller
{
    public function index(Request $request, TenantContext $tenants, StoreContext $stores, ListingAccess $access): Response
    {
        abort_unless($access->canView($request->user()), 403);
        $tenant = $tenants->get();
        $store = $stores->get();
        $search = trim((string) $request->query('search'));
        $defaultList = $tenant->defaultPriceList()->where('status', PriceListStatus::Active)->first();
        $storeDefaultList = $store->defaultPriceList()->where('status', PriceListStatus::Active)->first();
        $listings = $store->listings()->where('tenant_id', $tenant->getKey())
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('storefront_slug', 'ilike', "%{$search}%")
                    ->orWhereHas('product', fn ($products) => $products->where('name', 'ilike', "%{$search}%")
                        ->orWhereHas('variants', fn ($variants) => $variants->where('sku', 'ilike', "%{$search}%")));
            }))
            ->with(['product:id,name', 'priceList:id,name,currency,status'])
            ->withCount(['variants as enabled_variants_count' => fn ($query) => $query->where('is_enabled', true)])
            ->orderByDesc('created_at')->paginate(20)->withQueryString()
            ->through(function (StoreListing $listing) use ($store, $storeDefaultList, $defaultList): array {
                $effective = $listing->priceList?->status === PriceListStatus::Active
                    ? $listing->priceList
                    : ($listing->price_list_id === null
                        ? ($store->default_price_list_id === null ? $defaultList : $storeDefaultList)
                        : null);

                return [
                    'id' => $listing->getKey(), 'product' => $listing->product->name,
                    'status' => $listing->status->value, 'storefront_slug' => $listing->storefront_slug,
                    'enabled_variants_count' => $listing->enabled_variants_count,
                    'effective_price_list' => $effective === null ? null : "{$effective->name} ({$effective->currency})",
                    'published_at' => $listing->published_at?->toISOString(),
                ];
            });

        return Inertia::render('admin/listings/Index', [
            'listings' => $listings, 'filters' => ['search' => $search],
            'storeSettings' => ['default_price_list_id' => $store->default_price_list_id],
            'priceLists' => $this->activePriceLists($tenant),
        ]);
    }

    public function create(Request $request, TenantContext $tenants, StoreContext $stores, ListingAccess $access): Response
    {
        abort_unless($access->canCreate($request->user()), 403);
        $tenant = $tenants->get();
        $store = $stores->get();
        $search = trim((string) $request->query('product_search'));
        $listedIds = $store->listings()->pluck('product_id');
        $products = $tenant->products()->where('status', ProductStatus::Active)->whereNotIn('id', $listedIds)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'ilike', "%{$search}%")
                    ->orWhereHas('variants', fn ($variants) => $variants->where('sku', 'ilike', "%{$search}%"));
            }))->with('defaultVariant:id,product_id,sku')->orderBy('name')->limit(50)->get();

        return Inertia::render('admin/listings/Create', [
            'products' => $products->map(fn (Product $product) => [
                'id' => $product->getKey(), 'name' => $product->name, 'slug' => $product->slug,
                'sku' => $product->defaultVariant?->sku,
            ]),
            'priceLists' => $this->activePriceLists($tenant), 'productSearch' => $search,
        ]);
    }

    public function store(CreateListingRequest $request, TenantContext $tenants, StoreContext $stores, CreateListing $action): RedirectResponse
    {
        $listing = $action->handle($tenants->get(), $stores->get(), $request->validated());

        return to_route('admin.listings.edit', $listing)->with('status', 'Listing created.');
    }

    public function edit(
        Request $request, TenantContext $tenants, StoreContext $stores, ListingAccess $access,
        ListingPriceResolver $priceResolver, EffectivePriceCalculator $calculator, string $listing,
    ): Response {
        abort_unless($access->canView($request->user()), 403);
        $tenant = $tenants->get();
        $scoped = $stores->get()->listings()->where('tenant_id', $tenant->getKey())->with('product')->findOrFail($listing);
        $search = trim((string) $request->query('variant_search'));
        $variants = $scoped->product->variants()->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
            $query->where('sku', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%");
        }))->orderByDesc('is_default')->orderBy('sku')->paginate(20, ['*'], 'variants_page')->withQueryString();
        $rows = $scoped->variants()->whereIn('product_variant_id', $variants->getCollection()->modelKeys())->get()->keyBy('product_variant_id');
        $list = $priceResolver->priceListFor($scoped);
        $prices = $list === null ? collect() : $calculator->calculateMany($tenant, $variants->getCollection(), $list);
        $variants->through(function (ProductVariant $variant) use ($rows, $prices): array {
            $price = $prices->get($variant->getKey());

            return [
                'id' => $variant->getKey(), 'sku' => $variant->sku, 'name' => $variant->name,
                'status' => $variant->status->value, 'configured' => $rows->has($variant->getKey()),
                'is_enabled' => (bool) $rows->get($variant->getKey())?->is_enabled,
                'price' => $price?->toArray(),
            ];
        });

        return Inertia::render('admin/listings/Edit', [
            'listing' => [
                'id' => $scoped->getKey(), 'product' => $scoped->product->name,
                'status' => $scoped->status->value, 'storefront_slug' => $scoped->storefront_slug,
                'price_list_id' => $scoped->price_list_id,
                'effective_price_list' => $list === null ? null : ['id' => $list->getKey(), 'name' => $list->name, 'currency' => $list->currency],
                'published_at' => $scoped->published_at?->toISOString(),
            ],
            'variants' => $variants, 'filters' => ['variant_search' => $search],
            'enabledVariantIds' => $scoped->variants()->where('is_enabled', true)->pluck('product_variant_id'),
            'priceLists' => $this->activePriceLists($tenant),
        ]);
    }

    public function update(UpdateListingRequest $request, TenantContext $tenants, StoreContext $stores, UpdateListing $action, string $listing): RedirectResponse
    {
        $scoped = $stores->get()->listings()->where('tenant_id', $tenants->id())->findOrFail($listing);
        $action->handle($tenants->get(), $stores->get(), $scoped, $request->validated());

        return back()->with('status', 'Listing updated.');
    }

    public function publish(Request $request, TenantContext $tenants, StoreContext $stores, ListingAccess $access, PublishListing $action, string $listing): RedirectResponse
    {
        abort_unless($access->canPublish($request->user()), 403);
        $scoped = $stores->get()->listings()->where('tenant_id', $tenants->id())->findOrFail($listing);
        $action->handle($tenants->get(), $stores->get(), $scoped);

        return back()->with('status', 'Listing published.');
    }

    public function unpublish(Request $request, TenantContext $tenants, StoreContext $stores, ListingAccess $access, UnpublishListing $action, string $listing): RedirectResponse
    {
        abort_unless($access->canPublish($request->user()), 403);
        $scoped = $stores->get()->listings()->where('tenant_id', $tenants->id())->findOrFail($listing);
        $action->handle($tenants->get(), $stores->get(), $scoped);

        return back()->with('status', 'Listing unpublished.');
    }

    public function archive(Request $request, TenantContext $tenants, StoreContext $stores, ListingAccess $access, ArchiveListing $action, string $listing): RedirectResponse
    {
        abort_unless($access->canArchive($request->user()), 403);
        $scoped = $stores->get()->listings()->where('tenant_id', $tenants->id())->findOrFail($listing);
        $action->handle($tenants->get(), $stores->get(), $scoped);

        return to_route('admin.listings.index')->with('status', 'Listing archived.');
    }

    public function updateSettings(UpdateStoreListingSettingsRequest $request, TenantContext $tenants, StoreContext $stores, SetStoreDefaultPriceList $action): RedirectResponse
    {
        $action->handle($tenants->get(), $stores->get(), $request->validated('default_price_list_id'));

        return back()->with('status', 'Store listing settings updated.');
    }

    private function activePriceLists($tenant)
    {
        return $tenant->priceLists()->where('status', PriceListStatus::Active)->orderByDesc('is_default')->orderBy('name')
            ->get(['id', 'name', 'currency', 'is_default'])->map(fn (PriceList $list) => [
                'id' => $list->getKey(), 'name' => $list->name, 'currency' => $list->currency, 'is_default' => $list->is_default,
            ]);
    }
}
