<?php

namespace Tests\Feature\Orders;

use App\Models\User;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Orders\Application\CreateOrderFromCheckout;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Tests\Feature\Checkout\CheckoutFeatureTestCase;

abstract class OrdersFeatureTestCase extends CheckoutFeatureTestCase
{
    /** @return array{Order, CheckoutSession, mixed, mixed, mixed} */
    protected function readyOrder(Tenant $tenant, Store $store, ?User $actor = null, int $quantity = 2): array
    {
        [$checkout, , $listing, $variant, $level] = $this->pendingCheckout($store, quantity: $quantity);
        $ready = $this->makeReady($tenant, $checkout);
        $order = app(CreateOrderFromCheckout::class)->handle($tenant, $store, $ready, $actor);

        return [$order, $ready, $listing, $variant, $level];
    }
}
