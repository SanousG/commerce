<?php

namespace App\Modules\Storefront\Application;

use App\Modules\Cart\Domain\Models\Cart;

final class PublicCartContext
{
    private ?Cart $cart = null;

    public function set(?Cart $cart): void
    {
        $this->cart = $cart;
    }

    public function cart(): ?Cart
    {
        return $this->cart;
    }

    public function clear(): void
    {
        $this->cart = null;
    }
}
