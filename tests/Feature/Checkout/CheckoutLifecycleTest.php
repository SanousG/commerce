<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Cart\Application\AddCartItem;
use App\Modules\Cart\Application\CreateCart;
use App\Modules\Checkout\Application\CancelCheckout;
use App\Modules\Checkout\Application\ExpireCheckout;
use App\Modules\Checkout\Application\PrepareCheckout;
use App\Modules\Checkout\Application\StartCheckout;
use App\Modules\Checkout\Application\UpdateCheckout;
use App\Modules\Checkout\Domain\Enums\CheckoutStatus;
use App\Modules\Checkout\Domain\Models\CheckoutLine;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Promotions\Domain\Enums\PromotionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutLifecycleTest extends CheckoutFeatureTestCase
{
    public function test_start_is_idempotent_pending_and_does_not_reserve_inventory(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        [$checkout, $cart, , , $level] = $this->pendingCheckout($store);

        $same = app(StartCheckout::class)->handle($tenant, $store, $cart, $checkout->idempotency_key);

        $this->assertSame($checkout->getKey(), $same->getKey());
        $this->assertSame(CheckoutStatus::Pending, $checkout->status);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $checkout->token);
        $this->assertSame(0, $level->fresh()->reserved);
        $this->assertSame(0, InventoryReservation::query()->count());
        $this->expectException(ValidationException::class);
        app(StartCheckout::class)->handle($tenant, $store, $cart, (string) Str::uuid());
    }

    public function test_empty_and_expired_carts_cannot_start_checkout(): void
    {
        CarbonImmutable::setTestNow('2026-09-20 10:00:00');
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        $empty = app(CreateCart::class)->handle($store);
        try {
            app(StartCheckout::class)->handle($tenant, $store, $empty, (string) Str::uuid());
            $this->fail('An empty cart started checkout.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }

        [$cart, $listing, $variant] = $this->sellableCart($store);
        app(AddCartItem::class)->handle($cart, $listing, $variant, 1);
        $cart->update(['expires_at' => now()]);
        try {
            app(StartCheckout::class)->handle($tenant, $store, $cart->fresh(), (string) Str::uuid());
            $this->fail('An expired cart started checkout.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_prepare_snapshots_current_price_and_ready_snapshot_is_immutable(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$checkout, , , $variant, $level] = $this->pendingCheckout($this->createStore($tenant), quantity: 2);
        $this->priceVariant($tenant, $tenant->defaultPriceList()->sole(), $variant, '90.0000');

        $ready = $this->makeReady($tenant, $checkout);
        $line = $ready->lines()->sole();
        $this->assertSame(CheckoutStatus::Ready, $ready->status);
        $this->assertSame('90.0000', $line->base_unit_price);
        $this->assertSame('180.0000', $ready->grand_total);
        $this->assertSame(20, $level->fresh()->on_hand);
        $this->assertSame(2, $level->fresh()->reserved);

        $this->priceVariant($tenant, $tenant->defaultPriceList()->sole(), $variant, '80.0000');
        $this->assertSame('90.0000', $line->fresh()->base_unit_price);
        $this->expectException(ValidationException::class);
        app(UpdateCheckout::class)->handle($tenant, $ready, ['customer_name' => 'Changed']);
    }

    public function test_cart_quantity_at_prepare_is_snapshotted_and_later_cart_changes_do_not_mutate_it(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$checkout, $cart] = $this->pendingCheckout($this->createStore($tenant), quantity: 2);
        $cart->items()->sole()->update(['quantity' => 3]);
        $ready = $this->makeReady($tenant, $checkout);
        $this->assertSame(3, $ready->lines()->sole()->quantity);
        $cart->items()->sole()->update(['quantity' => 4]);
        $this->assertSame(3, $ready->lines()->sole()->quantity);
    }

    public function test_cancel_and_expire_release_reservations_without_changing_on_hand_and_are_idempotent(): void
    {
        CarbonImmutable::setTestNow('2026-09-20 10:00:00');
        $tenant = $this->createTenantFor(User::factory()->create());
        [$checkout, , , , $level] = $this->pendingCheckout($this->createStore($tenant), quantity: 3);
        $ready = $this->makeReady($tenant, $checkout);
        app(CancelCheckout::class)->handle($tenant, $ready);
        app(CancelCheckout::class)->handle($tenant, $ready->fresh());
        $this->assertSame(0, $level->fresh()->reserved);
        $this->assertSame(20, $level->fresh()->on_hand);

        [$other, , , , $otherLevel] = $this->pendingCheckout($this->createStore($tenant), quantity: 4);
        $otherReady = $this->makeReady($tenant, $other);
        CarbonImmutable::setTestNow($otherReady->expires_at);
        $expired = app(ExpireCheckout::class)->handle($tenant, $otherReady);
        app(ExpireCheckout::class)->handle($tenant, $expired);
        $this->assertSame(CheckoutStatus::Expired, $expired->status);
        $this->assertSame(0, $otherLevel->fresh()->reserved);
        $this->assertSame(20, $otherLevel->fresh()->on_hand);
        $this->assertSame(InventoryReservationStatus::Expired, InventoryReservation::query()
            ->where('reference_id', $otherReady->lines()->sole()->getKey())->sole()->status);
        CarbonImmutable::setTestNow();
    }

    public function test_allocator_splits_default_first_and_prepare_is_atomic_when_stock_is_insufficient(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$checkout, $cart, , $variant, $defaultLevel] = $this->pendingCheckout($this->createStore($tenant), quantity: 5, stock: 5);
        $secondary = InventoryLocation::create([
            'tenant_id' => $tenant->getKey(), 'name' => 'Secondary', 'code' => 'SECONDARY',
            'status' => InventoryLocationStatus::Active, 'is_default' => false,
        ]);
        InventoryLevel::create([
            'tenant_id' => $tenant->getKey(), 'inventory_location_id' => $secondary->getKey(),
            'product_variant_id' => $variant->getKey(), 'on_hand' => 10, 'reserved' => 0,
        ]);
        $cart->items()->sole()->update(['quantity' => 8]);
        $ready = $this->makeReady($tenant, $checkout);
        $reservations = $ready->lines()->sole()->reservations()->get()->keyBy('inventory_location_id');
        $this->assertSame(5, $reservations[$defaultLevel->inventory_location_id]->quantity);
        $this->assertSame(3, $reservations[$secondary->getKey()]->quantity);

        [$failed, $failedCart] = $this->pendingCheckout($this->createStore($tenant), quantity: 5, stock: 5);
        $failedCart->items()->sole()->update(['quantity' => 6]);
        app(UpdateCheckout::class)->handle($tenant, $failed, [
            'customer_name' => 'No Stock', 'email' => 'none@example.test', 'delivery_type' => 'PICKUP',
        ]);
        try {
            app(PrepareCheckout::class)->handle($tenant, $failed->fresh());
            $this->fail('Insufficient inventory was reserved.');
        } catch (ValidationException) {
            $this->assertSame(CheckoutStatus::Pending, $failed->fresh()->status);
            $this->assertSame(0, CheckoutLine::query()->where('checkout_session_id', $failed->getKey())->count());
        }
    }

    public function test_promotion_is_recalculated_then_frozen_in_ready_snapshot(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$checkout] = $this->pendingCheckout($this->createStore($tenant), price: '100.0000');
        $promotion = $this->createPromotion($tenant, ['name' => 'Checkout sale', 'discount_value' => '10']);

        $ready = $this->makeReady($tenant, $checkout);
        $line = $ready->lines()->sole();
        $this->assertSame($promotion->getKey(), $line->promotion_id);
        $this->assertSame('Checkout sale', $line->promotion_name);
        $this->assertSame('10.0000', $line->discount_unit_amount);
        $promotion->update(['name' => 'Changed sale', 'status' => PromotionStatus::Inactive]);
        $this->assertSame('Checkout sale', $line->fresh()->promotion_name);
        $this->assertSame('90.0000', $line->fresh()->final_unit_price);
    }

    public function test_competing_checkout_revalidates_locked_stock_and_cannot_oversell(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        [$first, , $listing, $variant, $level] = $this->pendingCheckout($store, quantity: 4, stock: 5);
        $secondCart = app(CreateCart::class)->handle($store);
        app(AddCartItem::class)->handle($secondCart, $listing, $variant, 4);
        $second = app(StartCheckout::class)->handle($tenant, $store, $secondCart, (string) Str::uuid());
        app(UpdateCheckout::class)->handle($tenant, $second, [
            'customer_name' => 'Second buyer', 'email' => 'second@example.test', 'delivery_type' => 'PICKUP',
        ]);

        $this->makeReady($tenant, $first);
        try {
            app(PrepareCheckout::class)->handle($tenant, $second->fresh());
            $this->fail('Competing checkout oversold inventory.');
        } catch (ValidationException) {
            $this->assertSame(4, $level->fresh()->reserved);
            $this->assertSame(1, $level->fresh()->available);
            $this->assertSame(1, InventoryReservation::query()->where('status', InventoryReservationStatus::Active)->count());
            $this->assertSame(CheckoutStatus::Pending, $second->fresh()->status);
        }
    }
}
