<?php

namespace Tests\Feature\Storefront;

use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Checkout\Domain\Enums\CheckoutStatus;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\StorePaymentSettings;
use App\Modules\Payments\Domain\Enums\CodDepositType;
use App\Modules\Pricing\Application\SetVariantPrice;
use App\Modules\Storefront\Application\PublicCartIdentity;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tax\Application\SaveTaxCategory;
use App\Modules\Tax\Application\SaveTaxRate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

class PublicCheckoutTest extends StorefrontFeatureTestCase
{
    public function test_public_checkout_routes_keep_web_csrf_rate_limits_and_session_locks(): void
    {
        foreach (['checkout.start', 'checkout.prepare'] as $name) {
            $route = app('router')->getRoutes()->getByName('storefront.development.'.$name);
            $middleware = $route->gatherMiddleware();

            $this->assertContains('web', $middleware);
            $this->assertContains('storefront.public', $middleware);
            $this->assertContains('storefront.cart', $middleware);
            $this->assertContains('throttle:public-checkout', $middleware);
            $this->assertSame(10, $route->locksFor());
            $this->assertSame(10, $route->waitsFor());
        }
    }

    public function test_guest_starts_prepares_and_recovers_authorized_checkout_without_creating_order_or_customer(): void
    {
        [$store, $publication, $cart] = $this->guestCart('checkout-ready', quantity: 2, price: '100.0000');
        $cookie = app(PublicCartIdentity::class)->cookieName($publication->public_handle);

        $this->withCookie($cookie, $cart->token)->post(route('storefront.development.checkout.start', $publication->public_handle), [
            'mutation_id' => (string) Str::uuid(),
        ])->assertRedirectToRoute('storefront.development.checkout.show', $publication->public_handle);

        $checkout = CheckoutSession::query()->sole();
        $this->assertSame(CheckoutStatus::Pending, $checkout->status);
        $this->assertNull($checkout->customer_id);
        $this->assertSame(0, InventoryReservation::query()->count());

        $this->withCookie($cookie, $cart->token)->post(
            route('storefront.development.checkout.prepare', $publication->public_handle),
            $this->checkoutPayload(),
        )->assertRedirectToRoute('storefront.development.checkout.show', $publication->public_handle);

        $ready = $checkout->fresh();
        $this->assertSame(CheckoutStatus::Ready, $ready->status);
        $this->assertSame('200.0000', $ready->subtotal);
        $this->assertSame('200.0000', $ready->grand_total);
        $this->assertSame('0.0000', $ready->shipping_total);
        $this->assertSame(1, $ready->tax_snapshot_version);
        $this->assertSame(2, $ready->lines()->sole()->quantity);
        $this->assertNull($ready->shipping_address_line_1);
        $this->assertSame('Av. Pickup 123', $ready->pickup_address_line_1);
        $this->assertSame('CASH', $ready->expected_payment_method->value);
        $this->assertSame('COD', $ready->payment_terms->value);
        $this->assertSame('NONE', $ready->cod_deposit_type->value);
        $this->assertSame('0.0000', $ready->cod_deposit_value);
        $this->assertSame(2, (int) InventoryReservation::query()->sum('quantity'));
        $this->assertSame(0, Customer::query()->count());
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Payment::query()->count());

        $response = $this->withCookie($cookie, $cart->token)
            ->get(route('storefront.development.checkout.show', $publication->public_handle));
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertInertia(fn (Assert $page) => $page
            ->component('storefront/Checkout')
            ->where('checkout.ready', true)
            ->where('checkout.totals.grandTotal', '200.0000')
            ->where('checkout.commercial.deliveryType', 'PICKUP')
            ->where('checkout.commercial.shippingConfigured', false)
            ->where('checkout.commercial.pickupConfigured', true)
            ->where('checkout.commercial.pickup.addressLine1', 'Av. Pickup 123')
            ->where('checkout.commercial.paymentMethods', ['CASH'])
            ->where('checkout.commercial.payment.terms', 'COD')
            ->where('checkout.commercial.payment.depositType', 'NONE')
            ->where('checkout.commercial.orderCreationAvailable', false)
            ->missing('checkout.id')
            ->missing('checkout.token')
            ->missing('checkout.cart_id')
            ->missing('store.id'));
        $this->assertStringNotContainsString((string) $ready->getKey(), $response->getContent());
        $this->assertStringNotContainsString($ready->token, $response->getContent());
        $this->assertStringNotContainsString('buyer@example.test', (string) $response->headers->get('Location'));
        $this->assertSame($store->getKey(), $ready->store_id);
    }

    public function test_checkout_validates_contact_delivery_and_reprices_before_reserving(): void
    {
        [, $publication, $cart, $product] = $this->guestCart('checkout-validation');
        $cookie = app(PublicCartIdentity::class)->cookieName($publication->public_handle);
        $this->withCookie($cookie, $cart->token)->post(route('storefront.development.checkout.start', $publication->public_handle), [
            'mutation_id' => (string) Str::uuid(),
        ]);

        $invalid = $this->checkoutPayload();
        $invalid['customer_name'] = '';
        $invalid['email'] = '';
        $invalid['phone'] = '';
        $this->withCookie($cookie, $cart->token)->post(
            route('storefront.development.checkout.prepare', $publication->public_handle), $invalid,
        )->assertSessionHasErrors(['customer_name', 'email', 'phone']);
        $this->assertSame(CheckoutStatus::Pending, CheckoutSession::query()->sole()->status);
        $this->assertSame(0, InventoryReservation::query()->count());

        app(SetVariantPrice::class)->handle(
            $product['product']->tenant()->firstOrFail(), $product['priceList'], $product['variant'], '125.0000',
        );
        $this->withCookie($cookie, $cart->token)->post(
            route('storefront.development.checkout.prepare', $publication->public_handle), $this->checkoutPayload(),
        )->assertRedirect();
        $this->assertSame('125.0000', CheckoutSession::query()->sole()->lines()->sole()->base_unit_price);
    }

    public function test_prepare_is_idempotent_and_rejects_reusing_mutation_with_different_personal_data(): void
    {
        [, $publication, $cart] = $this->guestCart('checkout-idempotent');
        $cookie = app(PublicCartIdentity::class)->cookieName($publication->public_handle);
        $this->withCookie($cookie, $cart->token)->post(route('storefront.development.checkout.start', $publication->public_handle), [
            'mutation_id' => (string) Str::uuid(),
        ]);
        $payload = $this->checkoutPayload();
        $route = route('storefront.development.checkout.prepare', $publication->public_handle);

        $this->withCookie($cookie, $cart->token)->post($route, $payload)->assertRedirect();
        $this->withCookie($cookie, $cart->token)->post($route, $payload)->assertRedirect();
        $this->assertSame(1, CheckoutSession::query()->count());
        $this->assertSame(1, InventoryReservation::query()->count());

        $payload['email'] = 'different@example.test';
        $this->withCookie($cookie, $cart->token)->post($route, $payload)
            ->assertSessionHasErrors('checkout');
        $this->assertSame('buyer@example.test', CheckoutSession::query()->sole()->email);
    }

    public function test_checkout_is_fail_closed_when_pickup_or_cash_cod_none_is_not_ready(): void
    {
        [, $publication, $cart] = $this->guestCart('checkout-pickup-blocked');
        $cookie = app(PublicCartIdentity::class)->cookieName($publication->public_handle);
        $cart->store->settings()->update(['pickup_enabled' => false]);

        $this->withCookie($cookie, $cart->token)->post(
            route('storefront.development.checkout.start', $publication->public_handle),
            ['mutation_id' => (string) Str::uuid()],
        )->assertSessionHasErrors('pickup');
        $this->assertSame(0, CheckoutSession::query()->count());

        $cart->store->settings()->update(['pickup_enabled' => true]);
        $cart->store->paymentSettings()->update([
            'cod_deposit_type' => CodDepositType::Percentage,
            'cod_deposit_value' => '10.0000',
        ]);
        $this->withCookie($cookie, $cart->token)->post(
            route('storefront.development.checkout.start', $publication->public_handle),
            ['mutation_id' => (string) Str::uuid()],
        )->assertSessionHasErrors('pickup');
        $this->assertSame(0, CheckoutSession::query()->count());
    }

    public function test_pickup_reserves_only_the_configured_active_tenant_location(): void
    {
        [$store, $publication, $cart, $product] = $this->guestCart('checkout-pickup-location', quantity: 2);
        $tenant = $store->tenant()->firstOrFail();
        $secondary = InventoryLocation::query()->create([
            'tenant_id' => $tenant->getKey(), 'name' => 'Pickup stock', 'code' => 'PICKUP',
            'status' => InventoryLocationStatus::Active, 'is_default' => false,
        ]);
        InventoryLevel::query()->create([
            'tenant_id' => $tenant->getKey(), 'inventory_location_id' => $secondary->getKey(),
            'product_variant_id' => $product['variant']->getKey(), 'on_hand' => 5, 'reserved' => 0,
        ]);
        $store->settings()->update(['pickup_inventory_location_id' => $secondary->getKey()]);
        $cookie = app(PublicCartIdentity::class)->cookieName($publication->public_handle);

        $this->withCookie($cookie, $cart->token)->post(
            route('storefront.development.checkout.start', $publication->public_handle),
            ['mutation_id' => (string) Str::uuid()],
        );
        $this->withCookie($cookie, $cart->token)->post(
            route('storefront.development.checkout.prepare', $publication->public_handle),
            $this->checkoutPayload(),
        )->assertRedirect();

        $reservations = InventoryReservation::query()->get();
        $this->assertCount(1, $reservations);
        $this->assertSame($secondary->getKey(), $reservations->sole()->inventory_location_id);
        $this->assertSame($secondary->getKey(), CheckoutSession::query()->sole()->pickup_inventory_location_id);
    }

    public function test_configuration_change_blocks_pending_prepare_without_inventory_or_payment_side_effects(): void
    {
        [$store, $publication, $cart] = $this->guestCart('checkout-pickup-change');
        $cookie = app(PublicCartIdentity::class)->cookieName($publication->public_handle);
        $this->withCookie($cookie, $cart->token)->post(
            route('storefront.development.checkout.start', $publication->public_handle),
            ['mutation_id' => (string) Str::uuid()],
        );
        $store->paymentSettings()->update(['cash_enabled' => false]);

        $this->withCookie($cookie, $cart->token)->post(
            route('storefront.development.checkout.prepare', $publication->public_handle),
            $this->checkoutPayload(),
        )->assertSessionHasErrors('pickup');

        $this->assertSame(CheckoutStatus::Pending, CheckoutSession::query()->sole()->status);
        $this->assertSame(0, InventoryReservation::query()->count());
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_empty_expired_foreign_store_and_foreign_tenant_carts_cannot_authorize_checkout(): void
    {
        [$storeA, $publicationA, $cartA] = $this->guestCart('checkout-scope-a');
        $storeB = $this->createStore($storeA->tenant()->firstOrFail());
        $publicationB = $this->publish($storeB, 'checkout-scope-b');
        $storeC = $this->createStore($this->createTenant());
        $publicationC = $this->publish($storeC, 'checkout-scope-c');
        $identity = app(PublicCartIdentity::class);
        $payload = ['mutation_id' => (string) Str::uuid()];

        $this->withCookie($identity->cookieName($publicationB->public_handle), $cartA->token)
            ->post(route('storefront.development.checkout.start', $publicationB->public_handle), $payload)
            ->assertNotFound();
        $this->withCookie($identity->cookieName($publicationC->public_handle), $cartA->token)
            ->post(route('storefront.development.checkout.start', $publicationC->public_handle), $payload)
            ->assertNotFound();
        $cartA->update(['expires_at' => now()->subMinute()]);
        $this->withCookie($identity->cookieName($publicationA->public_handle), $cartA->token)
            ->post(route('storefront.development.checkout.start', $publicationA->public_handle), $payload)
            ->assertNotFound();
        $this->assertSame(0, CheckoutSession::query()->count());
    }

    public function test_public_checkout_start_is_rate_limited_by_ip(): void
    {
        [, $publication, $cart] = $this->guestCart('checkout-rate-limit');
        $cookie = app(PublicCartIdentity::class)->cookieName($publication->public_handle);
        $route = route('storefront.development.checkout.start', $publication->public_handle);

        foreach (range(1, 40) as $attempt) {
            $this->withCookie($cookie, $cart->token)->post($route, [
                'mutation_id' => (string) Str::uuid(),
            ])->assertRedirect();
        }
        $this->withCookie($cookie, $cart->token)->post($route, [
            'mutation_id' => (string) Str::uuid(),
        ])->assertTooManyRequests();
        $this->assertSame(1, CheckoutSession::query()->count());
    }

    /** @return array{0: mixed, 1: mixed, 2: Cart, 3: array<string, mixed>} */
    private function guestCart(string $handle, int $quantity = 1, string $price = '100.0000'): array
    {
        $store = $this->createStore($this->createTenant());
        $publication = $this->publish($store, $handle);
        $product = $this->createPublishedProduct($store, compact('price') + ['stock' => 10]);
        $tenant = $store->tenant()->firstOrFail();
        $category = app(SaveTaxCategory::class)->create($tenant, [
            'code' => 'STANDARD', 'name' => 'Standard', 'status' => 'ACTIVE',
        ]);
        app(SaveTaxRate::class)->create($tenant, [
            'tax_category_id' => $category->getKey(), 'name' => 'IGV', 'rate' => '18.000000',
            'valid_from' => null, 'valid_until' => null, 'status' => 'ACTIVE',
        ]);
        $product['product']->update(['tax_category_id' => $category->getKey()]);
        $this->enablePickup($store);
        $this->post(route('storefront.development.cart.items.store', [
            $publication->public_handle, $product['listing']->storefront_slug,
        ]), [
            'variant_key' => hash_hmac('sha256', $product['variant']->getKey(), (string) config('app.key')),
            'quantity' => $quantity,
            'mutation_id' => (string) Str::uuid(),
        ]);

        return [$store, $publication, Cart::query()->sole(), $product];
    }

    private function enablePickup(Store $store): void
    {
        $tenant = $store->tenant()->firstOrFail();
        $location = $tenant->inventoryLocations()->where('is_default', true)->sole();
        $store->settings()->update([
            'pickup_enabled' => true,
            'pickup_inventory_location_id' => $location->getKey(),
            'pickup_instructions' => 'Present the pickup reference.',
            'address_line_1' => 'Av. Pickup 123',
            'district' => 'Miraflores',
            'province' => 'Lima',
            'department' => 'Lima',
            'country_code' => 'PE',
        ]);
        StorePaymentSettings::query()->create([
            'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(),
            'bank_transfer_enabled' => false, 'cash_enabled' => true, 'online_enabled' => false,
            'cod_enabled' => true, 'cod_deposit_type' => CodDepositType::None, 'cod_deposit_value' => '0.0000',
        ]);
    }

    /** @return array<string, mixed> */
    private function checkoutPayload(): array
    {
        return [
            'mutation_id' => (string) Str::uuid(),
            'customer_name' => 'Checkout Buyer', 'email' => 'buyer@example.test', 'phone' => '987654321',
            'billing_recipient_name' => 'Checkout Buyer', 'billing_phone' => '987654321',
            'billing_country_code' => 'PE', 'billing_region' => 'Lima', 'billing_city' => 'Lima',
            'billing_district' => 'Miraflores', 'billing_address_line_1' => 'Av. Billing 123',
            'billing_address_line_2' => null, 'billing_postal_code' => '15074', 'billing_reference' => null,
        ];
    }
}
