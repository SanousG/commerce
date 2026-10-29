<?php

namespace App\Modules\Storefront\Application;

use App\Modules\Catalog\Domain\Enums\CategoryStatus;
use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Inventory\Application\TenantInventoryAvailability;
use App\Modules\Listings\Application\ListingPriceResolver;
use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Media\Domain\Enums\MediaStatus;
use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Promotions\Application\EffectivePrice;
use App\Modules\Promotions\Application\EffectivePriceCalculator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PublicCatalog
{
    public const PAGE_SIZE = 12;

    public function __construct(
        private readonly ListingPriceResolver $listingPrices,
        private readonly EffectivePriceCalculator $effectivePrices,
        private readonly TenantInventoryAvailability $availability,
    ) {}

    /** @return array{categories: array<int, array<string, mixed>>, featuredProducts: array<int, array<string, mixed>>} */
    public function home(PublicStoreContext $context): array
    {
        $listings = $this->baseQuery($context)->latest('published_at')->limit(8)->get();

        return [
            'categories' => $this->categories($context)->take(4)->values()->all(),
            'featuredProducts' => $this->project($context, $listings)->values()->all(),
        ];
    }

    /** @return array{products: LengthAwarePaginator, categories: array<int, array<string, mixed>>, filters: array<string, ?string>} */
    public function catalog(PublicStoreContext $context, string $search = '', ?string $categorySlug = null): array
    {
        $query = $this->baseQuery($context)
            ->when($search !== '', fn (Builder $query) => $query->whereHas('product', fn (Builder $product) => $product
                ->where(fn (Builder $text) => $text
                    ->where('name', 'ilike', '%'.$this->escapeLike($search).'%')
                    ->orWhere('description', 'ilike', '%'.$this->escapeLike($search).'%'))))
            ->when($categorySlug !== null, fn (Builder $query) => $query->whereHas('product.categories', fn (Builder $category) => $category
                ->where('categories.tenant_id', $context->tenant()?->getKey())
                ->where('categories.status', CategoryStatus::Active)
                ->where('categories.slug', $categorySlug)));

        if ($categorySlug !== null && ! $this->categories($context)->contains('slug', $categorySlug)) {
            abort(404);
        }

        $paginator = $query->orderByDesc('published_at')->orderBy('id')->paginate(self::PAGE_SIZE)->withQueryString();
        $projected = $this->project($context, collect($paginator->items()));
        $paginator->setCollection($projected);

        return [
            'products' => $paginator,
            'categories' => $this->categories($context)->values()->all(),
            'filters' => ['search' => $search !== '' ? $search : null, 'category' => $categorySlug],
        ];
    }

    /** @return array<string, mixed> */
    public function product(PublicStoreContext $context, string $slug): array
    {
        $listing = $this->baseQuery($context)->where('storefront_slug', $slug)->firstOrFail();
        $projected = $this->project($context, collect([$listing]), true)->first();
        abort_unless(is_array($projected), 404);

        return $projected;
    }

    /** @return array{StoreListing, ProductVariant} */
    public function listingVariant(PublicStoreContext $context, string $slug, string $variantKey): array
    {
        $listing = $this->baseQuery($context)->where('storefront_slug', $slug)->firstOrFail();
        $row = $listing->variants->first(function ($row) use ($variantKey): bool {
            $key = hash_hmac('sha256', $row->product_variant_id, (string) config('app.key'));

            return hash_equals($key, $variantKey);
        });
        abort_unless($row !== null, 404);

        return [$listing, $row->productVariant];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function categories(PublicStoreContext $context): Collection
    {
        $productIds = $this->baseQuery($context)->select('product_id');

        return Category::query()
            ->where('tenant_id', $context->tenant()?->getKey())
            ->where('status', CategoryStatus::Active)
            ->whereHas('products', fn (Builder $query) => $query->whereIn('products.id', $productIds))
            ->withCount(['products' => fn (Builder $query) => $query->whereIn('products.id', $productIds)])
            ->orderBy('position')->orderBy('name')->get()
            ->map(fn (Category $category): array => [
                'name' => $category->name,
                'slug' => $category->slug,
                'productCount' => $category->products_count,
                'url' => $this->categoryUrl($context, $category->slug),
            ]);
    }

    public function baseQuery(PublicStoreContext $context): Builder
    {
        $store = $context->store();
        $tenant = $context->tenant();
        abort_unless($store !== null && $tenant !== null, 404);
        $fallbackListId = $store->default_price_list_id
            ?? $tenant->priceLists()->where('is_default', true)->where('status', PriceListStatus::Active)->value('id');
        abort_unless(is_string($fallbackListId), 404);

        return StoreListing::query()
            ->where('store_listings.tenant_id', $tenant->getKey())
            ->where('store_listings.store_id', $store->getKey())
            ->where('store_listings.status', StoreListingStatus::Published)
            ->whereHas('product', fn (Builder $query) => $query->where('status', ProductStatus::Active))
            ->whereExists(function ($query) use ($tenant, $fallbackListId) {
                $query->selectRaw('1')
                    ->from('store_listing_variants as public_slv')
                    ->join('product_variants as public_pv', function ($join) {
                        $join->on('public_pv.id', '=', 'public_slv.product_variant_id')
                            ->on('public_pv.product_id', '=', 'public_slv.product_id')
                            ->on('public_pv.tenant_id', '=', 'public_slv.tenant_id');
                    })
                    ->join('variant_prices as public_vp', function ($join) {
                        $join->on('public_vp.product_variant_id', '=', 'public_pv.id')
                            ->on('public_vp.tenant_id', '=', 'public_pv.tenant_id');
                    })
                    ->join('price_lists as public_pl', function ($join) {
                        $join->on('public_pl.id', '=', 'public_vp.price_list_id')
                            ->on('public_pl.tenant_id', '=', 'public_vp.tenant_id');
                    })
                    ->whereColumn('public_slv.store_listing_id', 'store_listings.id')
                    ->where('public_slv.tenant_id', $tenant->getKey())
                    ->where('public_slv.is_enabled', true)
                    ->where('public_pv.status', ProductVariantStatus::Active)
                    ->where('public_pl.status', PriceListStatus::Active)
                    ->whereRaw('public_vp.price_list_id = COALESCE(store_listings.price_list_id, ?)', [$fallbackListId]);
            })
            ->with($this->relations());
    }

    /** @param Collection<int, StoreListing> $listings
     * @return Collection<int, array<string, mixed>>
     */
    private function project(PublicStoreContext $context, Collection $listings, bool $detail = false): Collection
    {
        if ($listings->isEmpty()) {
            return collect();
        }

        $tenant = $context->tenant();
        abort_unless($tenant !== null, 404);
        $priceLists = $this->listingPrices->priceListsFor($listings);
        $variants = $listings->flatMap(fn (StoreListing $listing) => $listing->variants
            ->filter(fn ($row) => $row->is_enabled && $row->productVariant->status === ProductVariantStatus::Active)
            ->pluck('productVariant'))->unique('id')->values();
        $availability = $this->availability->forVariants($tenant, $variants);
        $effective = collect();

        foreach ($listings->groupBy(fn (StoreListing $listing) => $priceLists->get($listing->getKey())?->getKey() ?? '') as $listId => $group) {
            $list = $priceLists->get($group->first()->getKey());
            if ($list === null || $listId === '') {
                continue;
            }
            $groupVariants = $group->flatMap(fn (StoreListing $listing) => $listing->variants->pluck('productVariant'))
                ->unique('id')->values();
            $effective = $effective->merge($this->effectivePrices->calculateMany($tenant, $groupVariants, $list));
        }

        return $listings->map(function (StoreListing $listing) use ($context, $priceLists, $effective, $availability, $detail): ?array {
            $list = $priceLists->get($listing->getKey());
            if ($list === null) {
                return null;
            }
            $eligibleVariants = $listing->variants->filter(fn ($row) => $row->is_enabled
                && $row->productVariant->status === ProductVariantStatus::Active
                && $effective->get($row->product_variant_id) instanceof EffectivePrice);
            if ($eligibleVariants->isEmpty()) {
                return null;
            }
            $variantDtos = $eligibleVariants->map(function ($row) use ($listing, $list, $effective, $availability, $context): array {
                $variant = $row->productVariant;
                /** @var EffectivePrice $price */
                $price = $effective->get($variant->getKey());
                $available = max(0, (int) $availability->get($variant->getKey(), 0));

                return [
                    'key' => hash_hmac('sha256', $variant->getKey(), (string) config('app.key')),
                    'name' => $variant->name ?: $listing->product->name,
                    'isDefault' => $variant->is_default,
                    'options' => $variant->optionValues->map(fn ($value): array => [
                        'name' => $value->option->name,
                        'value' => $value->value,
                    ])->values()->all(),
                    'price' => $this->price($price, $list->currency),
                    'availability' => $this->availabilityDto($available),
                    'images' => $this->media($context, $variant->media, $listing->product->name),
                ];
            })->values();
            $displayVariant = $variantDtos->sortBy(fn (array $variant) => $variant['price']['finalAmount'])->first();
            $product = $listing->product;

            return [
                'slug' => $listing->storefront_slug,
                'url' => $this->productUrl($context, $listing->storefront_slug),
                'name' => $product->name,
                'description' => $detail ? $product->description : null,
                'categories' => $product->categories->map(fn (Category $category): array => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'url' => $this->categoryUrl($context, $category->slug),
                ])->values()->all(),
                'price' => $displayVariant['price'],
                'availability' => $this->availabilityDto($variantDtos->max('availability.availableUnits')),
                'image' => $this->media($context, $product->media, $product->name)->first(),
                ...($detail ? [
                    'images' => $this->media($context, $product->media, $product->name)->values()->all(),
                    'variants' => $variantDtos->all(),
                ] : []),
            ];
        })->filter()->values();
    }

    /** @return array<string, mixed> */
    private function price(EffectivePrice $price, string $currency): array
    {
        return [
            'baseAmount' => $price->baseAmount,
            'finalAmount' => $price->finalAmount,
            'discountAmount' => $price->totalDiscount,
            'currency' => $currency,
            'hasDiscount' => $price->promotion !== null,
            'promotionName' => $price->promotion?->name,
        ];
    }

    /** @return array{state: string, availableUnits: int} */
    private function availabilityDto(int $available): array
    {
        return ['state' => $available > 0 ? 'available' : 'unavailable', 'availableUnits' => max(0, $available)];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function media(PublicStoreContext $context, Collection $attachments, string $fallbackAlt): Collection
    {
        return $attachments->map(function ($attachment) use ($context, $fallbackAlt): ?array {
            $asset = $attachment->mediaAsset;
            if ($asset === null || $asset->status !== MediaStatus::Ready) {
                return null;
            }
            $thumbnail = $asset->derivatives->first(fn ($item) => ($item->variant->value ?? $item->variant) === 'THUMBNAIL');
            $medium = $asset->derivatives->first(fn ($item) => ($item->variant->value ?? $item->variant) === 'MEDIUM');
            if ($thumbnail === null && $medium === null) {
                return null;
            }
            $handle = $context->publication()?->public_handle;
            $url = fn ($derivative, string $variant): ?string => $derivative === null ? null : route('storefront.development.media', [
                'publicHandle' => $handle,
                'checksum' => $derivative->checksum,
                'variant' => $variant,
            ]);

            return [
                'thumbnailUrl' => $url($thumbnail, 'thumbnail'),
                'mediumUrl' => $url($medium, 'medium') ?? $url($thumbnail, 'thumbnail'),
                'width' => $medium?->width ?? $thumbnail?->width,
                'height' => $medium?->height ?? $thumbnail?->height,
                'alt' => $attachment->alt_text ?? $asset->alt_text ?? $fallbackAlt,
            ];
        })->filter()->values();
    }

    /** @return array<string, callable|string> */
    private function relations(): array
    {
        return [
            'store',
            'product.categories' => fn ($query) => $query->where('categories.status', CategoryStatus::Active)->orderBy('position'),
            'product.media.mediaAsset.derivatives',
            'product.options.values',
            'variants.productVariant.optionValues.option',
            'variants.productVariant.media.mediaAsset.derivatives',
        ];
    }

    private function productUrl(PublicStoreContext $context, string $slug): string
    {
        return route('storefront.development.products.show', [$context->publication()?->public_handle, $slug]);
    }

    private function categoryUrl(PublicStoreContext $context, string $slug): string
    {
        return route('storefront.development.categories.show', [$context->publication()?->public_handle, $slug]);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
