<?php

namespace App\Modules\Storefront\Application;

use App\Modules\Cart\Application\CreateCart;
use App\Modules\Cart\Domain\Models\Cart;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PublicCartMutation
{
    public function __construct(private readonly CreateCart $createCart) {}

    /** @param Closure(Cart): void $operation */
    public function handle(
        Request $request,
        PublicStoreContext $stores,
        PublicCartContext $carts,
        string $mutationId,
        bool $createIfMissing,
        Closure $operation,
    ): Cart {
        $store = $stores->store();
        abort_unless($store !== null, 404);
        $sessionKey = 'storefront.cart_mutations.'.hash('sha256', (string) $store->getKey());
        $completed = $request->session()->get($sessionKey, []);

        if (is_array($completed) && isset($completed[$mutationId])) {
            $cart = $store->carts()->where('tenant_id', $store->tenant_id)->findOrFail($completed[$mutationId]);
            $carts->set($cart);

            return $cart;
        }

        $cart = DB::transaction(function () use ($carts, $store, $createIfMissing, $operation): Cart {
            $cart = $carts->cart();
            if ($cart === null) {
                abort_unless($createIfMissing, 404);
                $cart = $this->createCart->handle($store);
            }

            $operation($cart);

            return $cart->fresh();
        });

        $completed = is_array($completed) ? $completed : [];
        $completed[$mutationId] = $cart->getKey();
        $request->session()->put($sessionKey, array_slice($completed, -50, null, true));
        $carts->set($cart);

        return $cart;
    }
}
