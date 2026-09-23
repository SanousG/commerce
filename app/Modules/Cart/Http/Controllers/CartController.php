<?php

namespace App\Modules\Cart\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Application\AddCartItem;
use App\Modules\Cart\Application\AttachCustomerToCart;
use App\Modules\Cart\Application\CartAccess;
use App\Modules\Cart\Application\CartCalculator;
use App\Modules\Cart\Application\ClearCart;
use App\Modules\Cart\Application\CreateCart;
use App\Modules\Cart\Application\RemoveCartItem;
use App\Modules\Cart\Application\UpdateCartItemQuantity;
use App\Modules\Cart\Domain\Enums\CartStatus;
use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Cart\Http\Requests\AddCartItemRequest;
use App\Modules\Cart\Http\Requests\AttachCartCustomerRequest;
use App\Modules\Cart\Http\Requests\CreateCartRequest;
use App\Modules\Cart\Http\Requests\ManageCartRequest;
use App\Modules\Cart\Http\Requests\UpdateCartItemRequest;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(Request $request, TenantContext $tenants, StoreContext $stores, CartAccess $access, CartCalculator $calculator): Response
    {
        abort_unless($access->canView($request->user()), 403);
        $search = trim((string) $request->query('search'));
        $carts = $stores->get()->carts()->where('tenant_id', $tenants->id())
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('token', 'ilike', $search.'%')
                    ->orWhereHas('customer', fn ($customer) => $customer->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%"));
            }))
            ->with(['customer', 'items'])
            ->orderByDesc('updated_at')->paginate(20)->withQueryString();
        $summaries = $calculator->calculateMany($carts->getCollection());
        $carts->setCollection($carts->getCollection()->map(fn (Cart $cart): array => [
            'id' => $cart->getKey(),
            'token_hint' => substr($cart->token, 0, 8).'...',
            'customer' => $cart->customer?->displayName(),
            'status' => $cart->status->value,
            'item_count' => $summaries[$cart->getKey()]->itemCount,
            'currency' => $summaries[$cart->getKey()]->currency,
            'subtotal' => $summaries[$cart->getKey()]->subtotal,
            'valid' => $summaries[$cart->getKey()]->valid,
            'expires_at' => $cart->expires_at?->toISOString(),
            'updated_at' => $cart->updated_at?->toISOString(),
        ]));

        return Inertia::render('admin/carts/Index', [
            'carts' => $carts,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(CreateCartRequest $request, TenantContext $tenants, StoreContext $stores, CreateCart $action): RedirectResponse
    {
        $customer = filled($request->validated('customer_id'))
            ? $tenants->get()->customers()->findOrFail($request->validated('customer_id'))
            : null;
        $cart = $action->handle($stores->get(), $customer);

        return to_route('admin.carts.show', $cart)->with('status', 'Cart created.');
    }

    public function show(Request $request, TenantContext $tenants, StoreContext $stores, CartAccess $access, CartCalculator $calculator, string $cart): Response
    {
        abort_unless($access->canView($request->user()), 403);
        $scoped = $this->cart($tenants, $stores, $cart);
        $summary = $calculator->calculate($scoped);
        $canManage = $access->canManage($request->user()) && $scoped->status === CartStatus::Active;
        $customers = $canManage
            ? $tenants->get()->customers()->orderBy('first_name')->orderBy('last_name')->limit(100)->get()
                ->map(fn (Customer $customer): array => ['id' => $customer->getKey(), 'name' => $customer->displayName()])
            : collect();
        $listings = $canManage
            ? $stores->get()->listings()->where('tenant_id', $tenants->id())
                ->where('status', StoreListingStatus::Published)->with(['product', 'variants.productVariant'])->orderBy('storefront_slug')->get()
                ->map(fn ($listing): array => [
                    'id' => $listing->getKey(),
                    'product' => $listing->product->name,
                    'variants' => $listing->variants->where('is_enabled', true)->map(fn ($listingVariant): array => [
                        'id' => $listingVariant->productVariant->getKey(),
                        'name' => $listingVariant->productVariant->name ?: $listingVariant->productVariant->sku,
                        'sku' => $listingVariant->productVariant->sku,
                    ])->values(),
                ])->filter(fn (array $listing) => count($listing['variants']) > 0)->values()
            : collect();

        return Inertia::render('admin/carts/Show', [
            'cart' => array_merge($summary->toArray(), [
                'store' => $stores->get()->name,
                'customer_id' => $scoped->customer_id,
                'customer' => $scoped->customer?->displayName(),
                'status' => $scoped->status->value,
                'token_hint' => substr($scoped->token, 0, 8).'...',
                'expires_at' => $scoped->expires_at?->toISOString(),
            ]),
            'customers' => $customers,
            'listings' => $listings,
            'canManage' => $canManage,
        ]);
    }

    public function addItem(AddCartItemRequest $request, TenantContext $tenants, StoreContext $stores, AddCartItem $action, string $cart): RedirectResponse
    {
        $scoped = $this->cart($tenants, $stores, $cart);
        $listing = $stores->get()->listings()->where('tenant_id', $tenants->id())->findOrFail($request->validated('store_listing_id'));
        $variant = $tenants->get()->productVariants()->findOrFail($request->validated('product_variant_id'));
        $action->handle($scoped, $listing, $variant, (int) $request->validated('quantity'));

        return back()->with('status', 'Item added.');
    }

    public function updateItem(UpdateCartItemRequest $request, TenantContext $tenants, StoreContext $stores, UpdateCartItemQuantity $action, string $cart, string $item): RedirectResponse
    {
        $scoped = $this->cart($tenants, $stores, $cart);
        $action->handle($scoped, $scoped->items()->findOrFail($item), (int) $request->validated('quantity'));

        return back()->with('status', 'Quantity updated.');
    }

    public function removeItem(ManageCartRequest $request, TenantContext $tenants, StoreContext $stores, RemoveCartItem $action, string $cart, string $item): RedirectResponse
    {
        $scoped = $this->cart($tenants, $stores, $cart);
        $action->handle($scoped, $scoped->items()->findOrFail($item));

        return back()->with('status', 'Item removed.');
    }

    public function clear(ManageCartRequest $request, TenantContext $tenants, StoreContext $stores, ClearCart $action, string $cart): RedirectResponse
    {
        $action->handle($this->cart($tenants, $stores, $cart));

        return back()->with('status', 'Cart cleared.');
    }

    public function attachCustomer(AttachCartCustomerRequest $request, TenantContext $tenants, StoreContext $stores, AttachCustomerToCart $action, string $cart): RedirectResponse
    {
        $customer = filled($request->validated('customer_id'))
            ? $tenants->get()->customers()->findOrFail($request->validated('customer_id'))
            : null;
        $action->handle($this->cart($tenants, $stores, $cart), $customer);

        return back()->with('status', 'Cart customer updated.');
    }

    private function cart(TenantContext $tenants, StoreContext $stores, string $id): Cart
    {
        return $stores->get()->carts()->where('tenant_id', $tenants->id())->with('customer')->findOrFail($id);
    }
}
