<?php

namespace Tests\Feature\Cart;

use App\Models\User;
use App\Modules\Cart\Application\AddCartItem;
use App\Modules\Cart\Application\AttachCustomerToCart;
use App\Modules\Cart\Application\ClearCart;
use App\Modules\Cart\Application\CreateCart;
use App\Modules\Cart\Application\FindCartByToken;
use App\Modules\Cart\Application\RemoveCartItem;
use App\Modules\Cart\Application\UpdateCartItemQuantity;
use App\Modules\Cart\Domain\Enums\CartStatus;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class CartMutationTest extends CartsFeatureTestCase
{
    public function test_guest_cart_is_secure_store_owned_and_customer_is_optional(): void
    {
        CarbonImmutable::setTestNow('2026-09-12 10:00:00');
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        $cart = app(CreateCart::class)->handle($store);

        $this->assertSame($tenant->getKey(), $cart->tenant_id);
        $this->assertSame($store->getKey(), $cart->store_id);
        $this->assertNull($cart->customer_id);
        $this->assertNull($cart->currency);
        $this->assertSame(CartStatus::Active, $cart->status);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $cart->token);
        $this->assertTrue($cart->expires_at->equalTo(now()->addHours(72)));
        CarbonImmutable::setTestNow();
    }

    public function test_add_increments_existing_line_and_never_mutates_inventory(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$cart, $listing, $variant, $level] = $this->sellableCart($this->createStore($tenant));
        $action = app(AddCartItem::class);

        $first = $action->handle($cart, $listing, $variant, 2);
        $second = $action->handle($cart->fresh(), $listing, $variant, 3);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(5, $second->quantity);
        $this->assertSame('PEN', $cart->fresh()->currency);
        $this->assertSame(20, $level->fresh()->on_hand);
        $this->assertSame(0, $level->fresh()->reserved);
        $this->assertSame(0, InventoryReservation::query()->count());
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_quantity_and_availability_are_enforced_but_decrease_does_not_reserve(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$cart, $listing, $variant, $level] = $this->sellableCart($this->createStore($tenant), stock: 5);
        $item = app(AddCartItem::class)->handle($cart, $listing, $variant, 5);

        foreach ([0, 10000] as $quantity) {
            try {
                app(UpdateCartItemQuantity::class)->handle($cart->fresh(), $item, $quantity);
                $this->fail('Invalid quantity was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('quantity', $exception->errors());
            }
        }
        $level->update(['on_hand' => 2]);
        app(UpdateCartItemQuantity::class)->handle($cart->fresh(), $item, 2);
        $this->assertSame(2, $item->fresh()->quantity);
        $this->assertSame(0, $level->fresh()->reserved);
    }

    public function test_remove_clear_and_customer_replacement_extend_active_cart(): void
    {
        CarbonImmutable::setTestNow('2026-09-12 10:00:00');
        $tenant = $this->createTenantFor(User::factory()->create());
        [$cart, $listing, $variant] = $this->sellableCart($this->createStore($tenant));
        $item = app(AddCartItem::class)->handle($cart, $listing, $variant, 1);
        $customerA = $this->createCustomer($tenant);
        $customerB = $this->createCustomer($tenant, 'second@example.test');

        CarbonImmutable::setTestNow('2026-09-12 11:00:00');
        app(AttachCustomerToCart::class)->handle($cart->fresh(), $customerA);
        app(AttachCustomerToCart::class)->handle($cart->fresh(), $customerB);
        $this->assertSame($customerB->getKey(), $cart->fresh()->customer_id);
        $this->assertTrue($cart->fresh()->expires_at->equalTo(now()->addHours(72)));
        app(RemoveCartItem::class)->handle($cart->fresh(), $item);
        $this->assertNull($cart->fresh()->currency);

        app(AddCartItem::class)->handle($cart->fresh(), $listing, $variant, 1);
        app(ClearCart::class)->handle($cart->fresh());
        $this->assertSame(0, $cart->items()->count());
        $this->assertNull($cart->fresh()->currency);
        CarbonImmutable::setTestNow();
    }

    public function test_cross_tenant_customer_and_expired_cart_are_rejected(): void
    {
        CarbonImmutable::setTestNow('2026-09-12 10:00:00');
        $tenant = $this->createTenantFor(User::factory()->create());
        [$cart, $listing, $variant, $level] = $this->sellableCart($this->createStore($tenant));
        $foreign = $this->createTenantFor(User::factory()->create());

        $this->expectException(ModelNotFoundException::class);
        try {
            app(AttachCustomerToCart::class)->handle($cart, $this->createCustomer($foreign, 'foreign@example.test'));
        } finally {
            $cart->update(['expires_at' => now()]);
            try {
                app(AddCartItem::class)->handle($cart->fresh(), $listing, $variant, 1);
            } catch (ValidationException) {
                $this->assertSame(CartStatus::Expired, $cart->fresh()->status);
                $this->assertSame(0, $level->fresh()->reserved);
            }
            CarbonImmutable::setTestNow();
        }
    }

    public function test_token_lookup_is_store_scoped_and_cannot_expire_a_terminal_cart(): void
    {
        CarbonImmutable::setTestNow('2026-09-12 10:00:00');
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        $otherStore = $this->createStore($tenant);
        $cart = app(CreateCart::class)->handle($store);
        $cart->update(['expires_at' => now()]);
        $stale = $cart->fresh();
        $cart->update(['status' => CartStatus::Converted]);

        $found = app(FindCartByToken::class)->handle($store, $stale->token);
        $this->assertSame(CartStatus::Converted, $found->status);
        try {
            app(FindCartByToken::class)->handle($otherStore, $stale->token);
            $this->fail('A token must not cross store boundaries.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
