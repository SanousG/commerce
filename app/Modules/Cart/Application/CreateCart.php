<?php

namespace App\Modules\Cart\Application;

use App\Modules\Cart\Domain\Enums\CartStatus;
use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Stores\Domain\Models\Store;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CreateCart
{
    public function __construct(private readonly CartState $state) {}

    public function handle(Store $store, ?Customer $customer = null): Cart
    {
        if ($customer !== null && $customer->tenant_id !== $store->tenant_id) {
            throw (new ModelNotFoundException)->setModel(Customer::class, [$customer->getKey()]);
        }

        return Cart::create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->getKey(),
            'customer_id' => $customer?->getKey(),
            'token' => bin2hex(random_bytes(32)),
            'status' => CartStatus::Active,
            'currency' => null,
            'expires_at' => now()->addHours($this->state->ttlHours()),
        ]);
    }
}
