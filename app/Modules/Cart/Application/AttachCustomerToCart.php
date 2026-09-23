<?php

namespace App\Modules\Cart\Application;

use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Customers\Domain\Models\Customer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class AttachCustomerToCart
{
    public function __construct(private readonly CartState $state) {}

    public function handle(Cart $cart, ?Customer $customer): Cart
    {
        $this->state->ensureEditable($cart);

        return DB::transaction(function () use ($cart, $customer): Cart {
            $locked = Cart::query()->where('tenant_id', $cart->tenant_id)->where('store_id', $cart->store_id)
                ->lockForUpdate()->findOrFail($cart->getKey());
            $this->state->ensureEditable($locked);
            if ($customer !== null && $customer->tenant_id !== $locked->tenant_id) {
                throw (new ModelNotFoundException)->setModel(Customer::class, [$customer->getKey()]);
            }
            $locked->update(['customer_id' => $customer?->getKey()]);
            $this->state->extend($locked);

            return $locked->refresh();
        });
    }
}
