<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Storefront\Application\PublicCartContext;
use App\Modules\Storefront\Application\PublicCheckout;
use App\Modules\Storefront\Application\PublicCheckoutPresenter;
use App\Modules\Storefront\Application\PublicStoreContext;
use App\Modules\Storefront\Application\PublicStorePresenter;
use App\Modules\Storefront\Http\Requests\PreparePublicCheckoutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class StorefrontCheckoutController extends Controller
{
    public function show(
        Request $request,
        PublicStoreContext $stores,
        PublicCartContext $carts,
        PublicCheckout $checkouts,
        PublicCheckoutPresenter $presenter,
        PublicStorePresenter $storePresenter,
    ): Response {
        $cart = $carts->cart();
        abort_unless($cart !== null, 404);
        $checkout = $checkouts->operational($stores, $cart);
        abort_unless($checkout !== null, 404);
        $publication = $stores->publication();
        abort_unless($publication !== null, 404);
        $store = $storePresenter->present($publication);

        return Inertia::render('storefront/Checkout', [
            'store' => $store,
            'checkout' => $presenter->present($checkout, $stores),
            'localization' => [
                'locale' => $store['locale'],
                'fallback' => config('localization.fallback'),
                'supported' => config('localization.supported'),
            ],
        ])->toResponse($request)->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function start(
        Request $request,
        string $publicHandle,
        PublicStoreContext $stores,
        PublicCartContext $carts,
        PublicCheckout $checkouts,
    ): RedirectResponse {
        $validated = $request->validate(['mutation_id' => ['required', 'uuid']]);
        $cart = $carts->cart();
        abort_unless($cart !== null, 404);
        $checkouts->start($stores, $cart, $validated['mutation_id']);

        return to_route('storefront.development.checkout.show', $publicHandle)
            ->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    public function prepare(
        PreparePublicCheckoutRequest $request,
        string $publicHandle,
        PublicStoreContext $stores,
        PublicCartContext $carts,
        PublicCheckout $checkouts,
    ): RedirectResponse {
        $cart = $carts->cart();
        abort_unless($cart !== null, 404);
        $checkouts->prepare($request, $stores, $cart, $request->validated());

        return to_route('storefront.development.checkout.show', $publicHandle)
            ->with('status', __('Checkout prepared. Inventory is reserved temporarily.'))
            ->withHeaders(['Cache-Control' => 'no-store, private']);
    }
}
