<?php

namespace App\Modules\Cart\Application;

use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Stores\Domain\Models\Store;

class FindCartByToken
{
    public function __construct(private readonly CartState $state) {}

    public function handle(Store $store, string $token): Cart
    {
        $cart = $store->carts()->where('tenant_id', $store->tenant_id)->where('token', $token)->firstOrFail();
        $this->state->expireIfNeeded($cart);

        return $cart;
    }
}
