<?php

namespace App\Modules\Promotions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Promotions\Application\ArchivePromotion;
use App\Modules\Promotions\Application\CreatePromotion;
use App\Modules\Promotions\Application\EffectivePriceCalculator;
use App\Modules\Promotions\Application\PromotionAccess;
use App\Modules\Promotions\Application\UpdatePromotion;
use App\Modules\Promotions\Domain\Enums\PromotionDiscountType;
use App\Modules\Promotions\Domain\Enums\PromotionScope;
use App\Modules\Promotions\Domain\Enums\PromotionStatus;
use App\Modules\Promotions\Domain\Models\Promotion;
use App\Modules\Promotions\Http\Requests\CreatePromotionRequest;
use App\Modules\Promotions\Http\Requests\UpdatePromotionRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function index(Request $request, TenantContext $context, PromotionAccess $access): Response
    {
        abort_unless($access->canView($request->user()), 403);
        $search = trim((string) $request->query('search'));
        $now = CarbonImmutable::now();
        $promotions = $context->get()->promotions()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'ilike', "%{$search}%")
                    ->orWhere('internal_code', 'ilike', "%{$search}%");
            }))
            ->withCount(['products', 'categories', 'variants'])
            ->orderByDesc('created_at')
            ->paginate(20)->withQueryString()
            ->through(fn (Promotion $promotion): array => $this->promotionData($promotion, $now));

        return Inertia::render('admin/promotions/Index', [
            'promotions' => $promotions,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(Request $request, TenantContext $context, PromotionAccess $access): Response
    {
        abort_unless($access->canCreate($request->user()), 403);

        return Inertia::render('admin/promotions/Create', $this->formData($context, $request));
    }

    public function store(CreatePromotionRequest $request, TenantContext $context, CreatePromotion $action): RedirectResponse
    {
        $promotion = $action->handle($context->get(), $request->validated());

        return to_route('admin.promotions.edit', $promotion)->with('status', 'Promotion created.');
    }

    public function edit(
        Request $request,
        TenantContext $context,
        PromotionAccess $access,
        EffectivePriceCalculator $calculator,
        string $promotion,
    ): Response {
        abort_unless($access->canView($request->user()), 403);
        $tenant = $context->get();
        $scoped = $tenant->promotions()->with(['products:id', 'categories:id', 'variants:id'])->findOrFail($promotion);
        $preview = null;
        if ($request->filled(['preview_variant', 'preview_price_list'])) {
            $variant = $tenant->productVariants()->findOrFail((string) $request->query('preview_variant'));
            $list = $tenant->priceLists()->findOrFail((string) $request->query('preview_price_list'));
            $preview = $calculator->calculate($tenant, $variant, $list)?->toArray();
        }

        return Inertia::render('admin/promotions/Edit', [
            ...$this->formData($context, $request, $scoped),
            'promotion' => $this->promotionData($scoped, CarbonImmutable::now()),
            'selected' => [
                'product_ids' => $scoped->products->modelKeys(),
                'category_ids' => $scoped->categories->modelKeys(),
                'variant_ids' => $scoped->variants->modelKeys(),
            ],
            'preview' => $preview,
            'previewSelection' => [
                'variant_id' => (string) $request->query('preview_variant', ''),
                'price_list_id' => (string) $request->query('preview_price_list', ''),
            ],
        ]);
    }

    public function update(UpdatePromotionRequest $request, TenantContext $context, UpdatePromotion $action, string $promotion): RedirectResponse
    {
        $scoped = $context->get()->promotions()->findOrFail($promotion);
        $action->handle($context->get(), $scoped, $request->validated());

        return back()->with('status', 'Promotion updated.');
    }

    public function archive(
        Request $request,
        TenantContext $context,
        PromotionAccess $access,
        ArchivePromotion $action,
        string $promotion,
    ): RedirectResponse {
        abort_unless($access->canArchive($request->user()), 403);
        $scoped = $context->get()->promotions()->findOrFail($promotion);
        $action->handle($context->get(), $scoped);

        return to_route('admin.promotions.index')->with('status', 'Promotion archived.');
    }

    private function formData(TenantContext $context, Request $request, ?Promotion $promotion = null): array
    {
        $tenant = $context->get();
        $search = trim((string) $request->query('target_search'));
        $selectedProducts = $promotion?->products->modelKeys() ?? [];
        $selectedCategories = $promotion?->categories->modelKeys() ?? [];
        $selectedVariants = $promotion?->variants->modelKeys() ?? [];

        $products = $tenant->products()->when($search !== '', fn ($query) => $query->where('name', 'ilike', "%{$search}%"))
            ->orderBy('name')->limit(50)->get(['id', 'name'])
            ->merge($tenant->products()->whereKey($selectedProducts)->get(['id', 'name']))->unique('id')->values();
        $categories = $tenant->categories()->when($search !== '', fn ($query) => $query->where('name', 'ilike', "%{$search}%"))
            ->orderBy('name')->limit(50)->get(['id', 'name'])
            ->merge($tenant->categories()->whereKey($selectedCategories)->get(['id', 'name']))->unique('id')->values();
        $variants = $tenant->productVariants()->with('product:id,name')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('sku', 'ilike', "%{$search}%")
                    ->orWhereHas('product', fn ($products) => $products->where('name', 'ilike', "%{$search}%"));
            }))->orderBy('sku')->limit(50)->get()
            ->merge($tenant->productVariants()->with('product:id,name')->whereKey($selectedVariants)->get())
            ->unique('id')->values();

        return [
            'statuses' => [PromotionStatus::Draft->value, PromotionStatus::Active->value, PromotionStatus::Inactive->value],
            'discountTypes' => array_column(PromotionDiscountType::cases(), 'value'),
            'scopes' => array_column(PromotionScope::cases(), 'value'),
            'currencies' => config('commerce.currencies', []),
            'targetSearch' => $search,
            'products' => $products->map(fn (Product $product) => ['id' => $product->getKey(), 'name' => $product->name]),
            'categories' => $categories->map(fn (Category $category) => ['id' => $category->getKey(), 'name' => $category->name]),
            'variants' => $variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->getKey(), 'sku' => $variant->sku, 'name' => $variant->name,
                'product' => $variant->product->name,
            ]),
            'priceLists' => $tenant->priceLists()->orderByDesc('is_default')->orderBy('name')
                ->get(['id', 'name', 'currency'])->map(fn (PriceList $list) => [
                    'id' => $list->getKey(), 'name' => $list->name, 'currency' => $list->currency,
                ]),
        ];
    }

    private function promotionData(Promotion $promotion, CarbonImmutable $now): array
    {
        return [
            'id' => $promotion->getKey(),
            'name' => $promotion->name,
            'internal_code' => $promotion->internal_code,
            'description' => $promotion->description,
            'status' => $promotion->status->value,
            'discount_type' => $promotion->discount_type->value,
            'discount_value' => $promotion->discount_value,
            'currency' => $promotion->currency,
            'starts_at' => $promotion->starts_at?->utc()->format('Y-m-d\TH:i'),
            'ends_at' => $promotion->ends_at?->utc()->format('Y-m-d\TH:i'),
            'priority' => $promotion->priority,
            'scope' => $promotion->scope->value,
            'is_currently_eligible' => $promotion->isEligibleAt($now),
            'products_count' => (int) ($promotion->products_count ?? $promotion->products()->count()),
            'categories_count' => (int) ($promotion->categories_count ?? $promotion->categories()->count()),
            'variants_count' => (int) ($promotion->variants_count ?? $promotion->variants()->count()),
            'created_at' => $promotion->created_at?->toISOString(),
        ];
    }
}
