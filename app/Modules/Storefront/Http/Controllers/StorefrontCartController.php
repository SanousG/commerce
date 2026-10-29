<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Application\AddCartItem;
use App\Modules\Cart\Application\CartState;
use App\Modules\Cart\Application\ClearCart;
use App\Modules\Cart\Application\RemoveCartItem;
use App\Modules\Cart\Application\UpdateCartItemQuantity;
use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Storefront\Application\PublicCartContext;
use App\Modules\Storefront\Application\PublicCartIdentity;
use App\Modules\Storefront\Application\PublicCartMutation;
use App\Modules\Storefront\Application\PublicCartPresenter;
use App\Modules\Storefront\Application\PublicCatalog;
use App\Modules\Storefront\Application\PublicStoreContext;
use App\Modules\Storefront\Application\PublicStorePresenter;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class StorefrontCartController extends Controller
{
    public function show(
        PublicStoreContext $stores,
        PublicCartContext $carts,
        PublicStorePresenter $storePresenter,
        PublicCartPresenter $cartPresenter,
    ): Response {
        $publication = $stores->publication();
        abort_unless($publication !== null, 404);
        $store = $storePresenter->present($publication);

        return Inertia::render('storefront/Cart', [
            'store' => $store,
            'cart' => $cartPresenter->present($carts->cart(), $stores),
            'localization' => [
                'locale' => $store['locale'],
                'fallback' => config('localization.fallback'),
                'supported' => config('localization.supported'),
            ],
        ]);
    }

    public function add(
        Request $request,
        string $publicHandle,
        string $listingSlug,
        PublicStoreContext $stores,
        PublicCartContext $carts,
        PublicCatalog $catalog,
        PublicCartMutation $mutations,
        PublicCartIdentity $identity,
        CartState $state,
        AddCartItem $add,
    ): RedirectResponse {
        $validated = $request->validate([
            'variant_key' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$state->maxQuantity()],
            'mutation_id' => ['required', 'uuid'],
        ]);
        [$listing, $variant] = $catalog->listingVariant($stores, $listingSlug, $validated['variant_key']);

        $cart = $this->mutate(
            $request,
            $stores,
            $carts,
            $mutations,
            $validated['mutation_id'],
            true,
            fn (Cart $cart) => $add->handle($cart, $listing, $variant, (int) $validated['quantity']),
        );

        return back()->with('status', __('Item added to cart.'))
            ->withCookie($identity->cookie($request, $stores, $cart));
    }

    public function update(
        Request $request,
        string $publicHandle,
        string $lineKey,
        PublicStoreContext $stores,
        PublicCartContext $carts,
        PublicCartPresenter $presenter,
        PublicCartMutation $mutations,
        PublicCartIdentity $identity,
        CartState $state,
        UpdateCartItemQuantity $update,
    ): RedirectResponse {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$state->maxQuantity()],
            'mutation_id' => ['required', 'uuid'],
        ]);
        $cart = $carts->cart();
        abort_unless($cart !== null, 404);
        $item = $presenter->resolveItem($cart, $lineKey);
        $cart = $this->mutate(
            $request, $stores, $carts, $mutations, $validated['mutation_id'], false,
            fn (Cart $cart) => $update->handle($cart, $item, (int) $validated['quantity']),
        );

        return back()->withCookie($identity->cookie($request, $stores, $cart));
    }

    public function remove(
        Request $request,
        string $publicHandle,
        string $lineKey,
        PublicStoreContext $stores,
        PublicCartContext $carts,
        PublicCartPresenter $presenter,
        PublicCartMutation $mutations,
        PublicCartIdentity $identity,
        RemoveCartItem $remove,
    ): RedirectResponse {
        $validated = $request->validate(['mutation_id' => ['required', 'uuid']]);
        $cart = $carts->cart();
        abort_unless($cart !== null, 404);
        $item = $presenter->resolveItem($cart, $lineKey);
        $cart = $this->mutate(
            $request, $stores, $carts, $mutations, $validated['mutation_id'], false,
            fn (Cart $cart) => $remove->handle($cart, $item),
        );

        return back()->withCookie($identity->cookie($request, $stores, $cart));
    }

    public function clear(
        Request $request,
        string $publicHandle,
        PublicStoreContext $stores,
        PublicCartContext $carts,
        PublicCartMutation $mutations,
        PublicCartIdentity $identity,
        ClearCart $clear,
    ): RedirectResponse {
        $validated = $request->validate(['mutation_id' => ['required', 'uuid']]);
        abort_unless($carts->cart() !== null, 404);
        $cart = $this->mutate(
            $request, $stores, $carts, $mutations, $validated['mutation_id'], false,
            fn (Cart $cart) => $clear->handle($cart),
        );

        return back()->withCookie($identity->cookie($request, $stores, $cart));
    }

    /** @param Closure(Cart): mixed $operation */
    private function mutate(
        Request $request,
        PublicStoreContext $stores,
        PublicCartContext $carts,
        PublicCartMutation $mutations,
        string $mutationId,
        bool $createIfMissing,
        Closure $operation,
    ): Cart {
        try {
            return $mutations->handle($request, $stores, $carts, $mutationId, $createIfMissing, $operation);
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'cart' => __('The cart could not be updated. Review availability and quantity.'),
            ]);
        }
    }
}
