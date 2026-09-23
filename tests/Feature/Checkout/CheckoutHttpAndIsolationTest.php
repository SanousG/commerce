<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Cart\Application\AddCartItem;
use App\Modules\Cart\Application\AttachCustomerToCart;
use App\Modules\Checkout\Application\CancelCheckout;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Customers\Application\CreateCustomerAddress;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

class CheckoutHttpAndIsolationTest extends CheckoutFeatureTestCase
{
    public function test_owner_and_admin_manage_while_member_only_views(): void
    {
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $user = User::factory()->create();
            $tenant = $this->createTenantFor($user, $role);
            [$checkout] = $this->pendingCheckout($store = $this->createStore($tenant));
            app(CancelCheckout::class)->handle($tenant, $checkout);
            $this->useContext($user, $tenant, $store);
            $this->get(route('admin.checkouts.index'))->assertOk();
            $this->get(route('admin.checkouts.create'))->assertOk();
        }

        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        [$checkout] = $this->pendingCheckout($store = $this->createStore($tenant));
        $this->useContext($member, $tenant, $store);
        $this->get(route('admin.checkouts.index'))->assertOk();
        $this->get(route('admin.checkouts.show', $checkout))->assertInertia(
            fn (AssertableInertia $page) => $page->where('canManage', false),
        );
        $this->post(route('admin.checkouts.cancel', $checkout))->assertForbidden();
    }

    public function test_show_and_cancel_are_strictly_store_and_tenant_scoped(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        [$checkoutA] = $this->pendingCheckout($storeA);
        [$checkoutB] = $this->pendingCheckout($this->createStore($tenant));
        $foreignTenant = $this->createTenantFor(User::factory()->create());
        [$foreign] = $this->pendingCheckout($this->createStore($foreignTenant));
        $this->useContext($user, $tenant, $storeA);

        $this->get(route('admin.checkouts.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/checkouts/Index')->has('checkouts.data', 1)->where('checkouts.data.0.id', $checkoutA->getKey())
            ->missing('checkouts.data.0.token'));
        $this->get(route('admin.checkouts.show', $checkoutB))->assertNotFound();
        $this->get(route('admin.checkouts.show', $foreign))->assertNotFound();
        $this->post(route('admin.checkouts.cancel', $checkoutB))->assertNotFound();
    }

    public function test_customer_address_is_tenant_scoped_and_snapshotted(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        [$cart, $listing, $variant] = $this->sellableCart($store);
        app(AddCartItem::class)->handle($cart, $listing, $variant, 1);
        $customer = $this->createCustomer($tenant, 'address@example.test');
        app(AttachCustomerToCart::class)->handle($cart->fresh(), $customer);
        $address = app(CreateCustomerAddress::class)->handle($tenant, $customer, [
            'label' => 'Home', 'recipient_name' => 'Snapshot Recipient', 'phone' => '+51999999999',
            'country_code' => 'PE', 'region' => 'Lima', 'city' => 'Lima', 'district' => 'Miraflores',
            'address_line_1' => 'Original 123', 'address_line_2' => null, 'postal_code' => '15074',
            'reference' => 'Blue door', 'is_default_shipping' => true, 'is_default_billing' => true,
        ]);
        $this->useContext($user, $tenant, $store);
        $this->post(route('admin.checkouts.store'), [
            'cart_id' => $cart->getKey(), 'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();
        $checkout = CheckoutSession::query()->where('cart_id', $cart->getKey())->sole();
        $this->patch(route('admin.checkouts.update', $checkout), [
            'customer_name' => 'Buyer', 'email' => 'buyer@example.test', 'delivery_type' => 'SHIPPING',
            'shipping_address_id' => $address->getKey(), 'billing_same_as_shipping' => true,
        ])->assertRedirect();
        $this->assertSame('Original 123', $checkout->fresh()->shipping_address_line_1);
        $this->assertSame('Original 123', $checkout->fresh()->billing_address_line_1);
        $address->update(['address_line_1' => 'Changed 999']);
        $this->assertSame('Original 123', $checkout->fresh()->shipping_address_line_1);

        $foreignTenant = $this->createTenantFor(User::factory()->create());
        $foreignCustomer = $this->createCustomer($foreignTenant, 'foreign-address@example.test');
        $foreignAddress = app(CreateCustomerAddress::class)->handle($foreignTenant, $foreignCustomer, [
            'country_code' => 'PE', 'city' => 'Lima', 'address_line_1' => 'Foreign 1',
        ]);
        $this->patch(route('admin.checkouts.update', $checkout), [
            'customer_name' => 'Buyer', 'email' => 'buyer@example.test', 'delivery_type' => 'SHIPPING',
            'shipping_address_id' => $foreignAddress->getKey(),
        ])->assertNotFound();
    }
}
