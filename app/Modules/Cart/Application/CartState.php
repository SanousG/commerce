<?php

namespace App\Modules\Cart\Application;

use App\Modules\Cart\Domain\Enums\CartStatus;
use App\Modules\Cart\Domain\Models\Cart;
use Illuminate\Validation\ValidationException;

class CartState
{
    public function ensureEditable(Cart $cart): void
    {
        $this->expireIfNeeded($cart);

        if ($cart->status !== CartStatus::Active) {
            throw ValidationException::withMessages(['cart' => 'The cart is not editable.']);
        }
    }

    public function expireIfNeeded(Cart $cart): void
    {
        if ($cart->status === CartStatus::Active && $cart->expires_at?->lte(now())) {
            Cart::query()->whereKey($cart->getKey())
                ->where('tenant_id', $cart->tenant_id)
                ->where('store_id', $cart->store_id)
                ->where('status', CartStatus::Active)
                ->where('expires_at', '<=', now())
                ->update(['status' => CartStatus::Expired]);
            $cart->refresh();
        }
    }

    public function extend(Cart $cart): void
    {
        $cart->update(['expires_at' => now()->addHours($this->ttlHours())]);
    }

    public function ttlHours(): int
    {
        return max(1, (int) config('commerce.cart_ttl_hours', 72));
    }

    public function maxQuantity(): int
    {
        return min(9999, max(1, (int) config('commerce.cart_max_item_quantity', 9999)));
    }
}
