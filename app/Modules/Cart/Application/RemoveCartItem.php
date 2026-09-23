<?php

namespace App\Modules\Cart\Application;

use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Cart\Domain\Models\CartItem;
use Illuminate\Support\Facades\DB;

class RemoveCartItem
{
    public function __construct(private readonly CartState $state) {}

    public function handle(Cart $cart, CartItem $item): void
    {
        $this->state->ensureEditable($cart);

        DB::transaction(function () use ($cart, $item): void {
            $locked = Cart::query()->where('tenant_id', $cart->tenant_id)->where('store_id', $cart->store_id)
                ->lockForUpdate()->findOrFail($cart->getKey());
            $this->state->ensureEditable($locked);
            $locked->items()->findOrFail($item->getKey())->delete();
            if (! $locked->items()->exists()) {
                $locked->update(['currency' => null]);
            }
            $this->state->extend($locked);
        });
    }
}
