<?php

namespace App\Modules\Cart\Application;

use App\Modules\Cart\Domain\Models\Cart;
use Illuminate\Support\Facades\DB;

class ClearCart
{
    public function __construct(private readonly CartState $state) {}

    public function handle(Cart $cart): void
    {
        $this->state->ensureEditable($cart);

        DB::transaction(function () use ($cart): void {
            $locked = Cart::query()->where('tenant_id', $cart->tenant_id)->where('store_id', $cart->store_id)
                ->lockForUpdate()->findOrFail($cart->getKey());
            $this->state->ensureEditable($locked);
            $locked->items()->delete();
            $locked->update(['currency' => null]);
            $this->state->extend($locked);
        });
    }
}
