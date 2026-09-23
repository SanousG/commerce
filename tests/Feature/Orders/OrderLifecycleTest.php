<?php

namespace Tests\Feature\Orders;

use App\Models\User;
use App\Modules\Cart\Domain\Enums\CartStatus;
use App\Modules\Checkout\Domain\Enums\CheckoutStatus;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Orders\Application\CancelOrder;
use App\Modules\Orders\Application\CreateOrderFromCheckout;
use App\Modules\Orders\Domain\Enums\OrderFulfillmentStatus;
use App\Modules\Orders\Domain\Enums\OrderPaymentStatus;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class OrderLifecycleTest extends OrdersFeatureTestCase
{
    public function test_ready_checkout_creates_exact_confirmed_snapshot_without_committing_inventory(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        [$checkout, $cart, $listing, $variant, $level] = $this->pendingCheckout($store, quantity: 3, price: '90.0000');
        $ready = $this->makeReady($tenant, $checkout);
        $line = $ready->lines()->sole();

        $listing->product()->firstOrFail()->update(['name' => 'Changed current product']);
        $variant->update(['sku' => 'CHANGED-CURRENT-SKU']);
        $this->priceVariant($tenant, $tenant->defaultPriceList()->sole(), $variant, '80.0000');
        $order = app(CreateOrderFromCheckout::class)->handle($tenant, $store, $ready, $user);
        $item = $order->items()->sole();

        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(OrderPaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame(OrderFulfillmentStatus::Unfulfilled, $order->fulfillment_status);
        $this->assertSame(CheckoutStatus::Completed, $ready->fresh()->status);
        $this->assertNotNull($ready->fresh()->completed_at);
        $this->assertSame(CartStatus::Converted, $cart->fresh()->status);
        $this->assertSame($line->product_name, $item->product_name);
        $this->assertSame($line->sku, $item->sku);
        $this->assertSame('90.0000', $item->base_unit_price);
        $this->assertSame($ready->grand_total, $order->grand_total);
        $this->assertSame(20, $level->fresh()->on_hand);
        $this->assertSame(3, $level->fresh()->reserved);
        $this->assertSame(InventoryReservationStatus::Active, $line->reservations()->sole()->status);
        $history = $order->history()->sole();
        $this->assertNull($history->from_status);
        $this->assertSame(OrderStatus::Confirmed, $history->to_status);
        $this->assertSame($user->getKey(), $history->actor_user_id);

        $ready->update(['customer_name' => 'Later checkout mutation']);
        $this->assertSame('Checkout Buyer', $order->fresh()->customer_name);
    }

    public function test_creation_is_idempotent_and_numbers_are_incremental_per_tenant(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        [$checkout] = $this->pendingCheckout($store);
        $ready = $this->makeReady($tenant, $checkout);
        $first = app(CreateOrderFromCheckout::class)->handle($tenant, $store, $ready);
        $same = app(CreateOrderFromCheckout::class)->handle($tenant, $store, $ready->fresh());
        [$second] = $this->readyOrder($tenant, $store);

        $this->assertSame($first->getKey(), $same->getKey());
        $this->assertSame('ORD-000001', $first->order_number);
        $this->assertSame('ORD-000002', $second->order_number);
        $this->assertSame(2, Order::query()->where('tenant_id', $tenant->getKey())->count());

        $otherTenant = $this->createTenantFor(User::factory()->create());
        [$other] = $this->readyOrder($otherTenant, $this->createStore($otherTenant));
        $this->assertSame('ORD-000001', $other->order_number);
    }

    public function test_non_ready_or_expired_checkout_cannot_create_order(): void
    {
        CarbonImmutable::setTestNow('2026-09-23 10:00:00');
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        [$pending] = $this->pendingCheckout($store);
        try {
            app(CreateOrderFromCheckout::class)->handle($tenant, $store, $pending);
            $this->fail('Pending checkout created an order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('checkout', $exception->errors());
        }

        foreach ([CheckoutStatus::Expired, CheckoutStatus::Cancelled] as $status) {
            [$terminal] = $this->pendingCheckout($store);
            $terminal->update(['status' => $status]);
            try {
                app(CreateOrderFromCheckout::class)->handle($tenant, $store, $terminal->fresh());
                $this->fail("{$status->value} checkout created an order.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('checkout', $exception->errors());
            }
        }

        $ready = $this->makeReady($tenant, $pending);
        CarbonImmutable::setTestNow($ready->expires_at);
        try {
            app(CreateOrderFromCheckout::class)->handle($tenant, $store, $ready->fresh());
            $this->fail('Expired ready checkout created an order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('checkout', $exception->errors());
            $this->assertSame(0, Order::query()->count());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_missing_active_reservation_rolls_back_all_order_transitions(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        [$checkout, $cart] = $this->pendingCheckout($store);
        $ready = $this->makeReady($tenant, $checkout);
        $ready->lines()->sole()->reservations()->sole()->update([
            'status' => InventoryReservationStatus::Released, 'released_at' => now(),
        ]);

        try {
            app(CreateOrderFromCheckout::class)->handle($tenant, $store, $ready);
            $this->fail('Order was created without active reservations.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('inventory', $exception->errors());
            $this->assertSame(CheckoutStatus::Ready, $ready->fresh()->status);
            $this->assertSame(CartStatus::Active, $cart->fresh()->status);
            $this->assertSame(0, Order::query()->count());
        }
    }

    public function test_cancel_is_idempotent_releases_reservations_and_preserves_checkout_and_cart(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        [$order, $checkout, , , $level] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $user, 3);
        $cancelled = app(CancelOrder::class)->handle($tenant, $order, 'Buyer requested cancellation.', $user);
        app(CancelOrder::class)->handle($tenant, $cancelled, 'Repeated request.', $user);

        $this->assertSame(OrderStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame(20, $level->fresh()->on_hand);
        $this->assertSame(0, $level->fresh()->reserved);
        $this->assertSame(InventoryReservationStatus::Released, $checkout->lines()->sole()->reservations()->sole()->status);
        $this->assertSame(CheckoutStatus::Completed, $checkout->fresh()->status);
        $this->assertSame(CartStatus::Converted, $checkout->cart()->firstOrFail()->status);
        $this->assertSame(2, $cancelled->history()->count());
        $history = $cancelled->history()->latest('created_at')->firstOrFail();
        $this->assertSame(OrderStatus::Confirmed, $history->from_status);
        $this->assertSame(OrderStatus::Cancelled, $history->to_status);
        $this->assertSame('Buyer requested cancellation.', $history->reason);
    }
}
