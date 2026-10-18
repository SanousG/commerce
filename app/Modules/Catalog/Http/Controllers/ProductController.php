<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Catalog\Application\ArchiveProduct;
use App\Modules\Catalog\Application\CatalogAccess;
use App\Modules\Catalog\Application\CreateProduct;
use App\Modules\Catalog\Application\UpdateProduct;
use App\Modules\Catalog\Domain\Enums\CategoryStatus;
use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Catalog\Http\Requests\CreateProductRequest;
use App\Modules\Catalog\Http\Requests\UpdateProductRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request, TenantContext $context, CatalogAccess $access): Response
    {
        abort_unless($access->canViewProducts($request->user()), 403);

        $tenant = $context->get();
        $search = trim((string) $request->query('search'));
        $products = $tenant->products()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'ilike', "%{$search}%")
                    ->orWhereHas('variants', fn ($variants) => $variants->where('sku', 'ilike', "%{$search}%"));
            }))
            ->with([
                'defaultVariant:id,product_id,sku',
                'categories:id,name',
            ])
            ->withCount('variants')
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product): array => [
                'id' => $product->getKey(),
                'name' => $product->name,
                'slug' => $product->slug,
                'status' => $product->status->value,
                'default_sku' => $product->defaultVariant?->sku,
                'variants_count' => $product->variants_count,
                'categories' => $product->categories->pluck('name')->all(),
            ]);

        return Inertia::render('admin/products/Index', [
            'products' => $products,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(Request $request, TenantContext $context, CatalogAccess $access): Response
    {
        abort_unless($access->canCreateProducts($request->user()), 403);

        return Inertia::render('admin/products/Create', [
            'categories' => $this->categoryChoices($context),
        ]);
    }

    public function store(CreateProductRequest $request, TenantContext $context, CreateProduct $action): RedirectResponse
    {
        $product = $action->handle($context->get(), $request->safe()->only([
            'name', 'description', 'sku', 'barcode', 'categories',
        ]), $request->user());

        return to_route('admin.products.edit', $product)->with('status', 'Product created.');
    }

    public function edit(
        Request $request,
        TenantContext $context,
        CatalogAccess $access,
        PermissionChecker $permissions,
        string $product,
    ): Response {
        abort_unless($access->canViewProducts($request->user()), 403);
        $canViewMedia = $permissions->userCan($request->user(), PermissionKey::MediaView);

        $scopedProduct = $context->get()->products()
            ->with([
                'categories:id',
                'options.values',
                'media.mediaAsset',
                'variants' => fn ($query) => $query->with(['optionValues.option', 'media.mediaAsset'])->orderByDesc('is_default')->orderBy('name'),
            ])
            ->findOrFail($product);
        $canViewPricing = $permissions->userCan($request->user(), PermissionKey::PricingView);
        $defaultPriceList = $canViewPricing
            ? $context->get()->priceLists()
                ->where('is_default', true)
                ->with(['prices' => fn ($query) => $query->whereIn('product_variant_id', $scopedProduct->variants->modelKeys())])
                ->first()
            : null;
        $defaultPrices = $defaultPriceList?->prices->keyBy('product_variant_id');

        return Inertia::render('admin/products/Edit', [
            'product' => [
                'id' => $scopedProduct->getKey(),
                'name' => $scopedProduct->name,
                'slug' => $scopedProduct->slug,
                'description' => $scopedProduct->description,
                'status' => $scopedProduct->status->value,
                'tax_category_id' => $scopedProduct->tax_category_id,
                'category_ids' => $scopedProduct->categories->modelKeys(),
                'media' => $canViewMedia ? $scopedProduct->media->map(fn ($item) => [
                    'id' => $item->mediaAsset->getKey(),
                    'title' => $item->mediaAsset->title,
                    'alt_text' => $item->alt_text,
                    'default_alt_text' => $item->mediaAsset->alt_text,
                    'is_primary' => $item->is_primary,
                    'position' => $item->position,
                    'thumbnail_url' => route('admin.media.thumbnail', $item->mediaAsset),
                ])->values() : [],
                'options' => $scopedProduct->options->map(fn ($option) => [
                    'id' => $option->getKey(),
                    'name' => $option->name,
                    'values' => $option->values->map(fn ($value) => [
                        'id' => $value->getKey(),
                        'value' => $value->value,
                    ]),
                ]),
                'variants' => $scopedProduct->variants->map(fn ($variant) => [
                    'id' => $variant->getKey(),
                    'name' => $variant->name,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'status' => $variant->status->value,
                    'is_default' => $variant->is_default,
                    'option_value_ids' => $variant->optionValues->modelKeys(),
                    'display_options' => $variant->optionValues->map(
                        fn ($value) => $value->option->name.': '.$value->value,
                    )->values(),
                    'default_price' => $defaultPrices?->get($variant->getKey())?->amount,
                    'media' => $canViewMedia ? $variant->media->map(fn ($item) => [
                        'id' => $item->mediaAsset->getKey(),
                        'title' => $item->mediaAsset->title,
                        'alt_text' => $item->mediaAsset->alt_text,
                        'position' => $item->position,
                        'thumbnail_url' => route('admin.media.thumbnail', $item->mediaAsset),
                    ])->values() : [],
                ]),
            ],
            'pricing' => $defaultPriceList === null ? null : [
                'price_list_id' => $defaultPriceList->getKey(),
                'name' => $defaultPriceList->name,
                'currency' => $defaultPriceList->currency,
                'can_update' => $permissions->userCan($request->user(), PermissionKey::PricingUpdate),
            ],
            'categories' => $this->categoryChoices($context),
            'taxCategories' => $request->user()->can('taxes.view') || $request->user()->can('taxes.manage')
                ? $context->get()->taxCategories()->where('status', 'ACTIVE')->orderBy('name')
                    ->get(['id', 'name', 'code'])->map(fn ($category): array => [
                        'id' => $category->getKey(), 'name' => $category->name, 'code' => $category->code->value,
                    ])->all()
                : [],
            'productStatuses' => array_column(ProductStatus::cases(), 'value'),
            'variantStatuses' => array_column(ProductVariantStatus::cases(), 'value'),
            'mediaPermissions' => [
                'view' => $canViewMedia,
                'upload' => $permissions->userCan($request->user(), PermissionKey::MediaUpload),
            ],
        ]);
    }

    public function update(
        UpdateProductRequest $request,
        TenantContext $context,
        UpdateProduct $action,
        string $product,
    ): RedirectResponse {
        $scopedProduct = $context->get()->products()->findOrFail($product);
        $action->handle($context->get(), $scopedProduct, $request->safe()->only([
            'name', 'description', 'status', 'tax_category_id', 'categories',
        ]), $request->user());

        return back()->with('status', 'Product updated.');
    }

    public function archive(
        Request $request,
        TenantContext $context,
        CatalogAccess $access,
        ArchiveProduct $action,
        string $product,
    ): RedirectResponse {
        abort_unless($access->canArchiveProducts($request->user()), 403);
        $scopedProduct = $context->get()->products()->findOrFail($product);
        $action->handle($context->get(), $scopedProduct, $request->user());

        return to_route('admin.products.index')->with('status', 'Product archived.');
    }

    private function categoryChoices(TenantContext $context): array
    {
        return $context->get()->categories()
            ->where('status', '!=', CategoryStatus::Archived)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->map(fn ($category): array => [
                'id' => $category->getKey(),
                'name' => $category->name,
                'parent_id' => $category->parent_id,
            ])->all();
    }
}
