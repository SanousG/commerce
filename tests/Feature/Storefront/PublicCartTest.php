<?php

namespace Tests\Feature\Storefront;

use App\Modules\Cart\Domain\Enums\CartStatus;
use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Promotions\Application\CreatePromotion;
use App\Modules\Storefront\Application\PublicCartIdentity;
use App\Modules\Tax\Application\SaveTaxCategory;
use App\Modules\Tax\Application\SaveTaxRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

class PublicCartTest extends StorefrontFeatureTestCase
{
    public function test_public_cart_routes_are_csrf_protected_rate_limited_and_session_blocked(): void
    {
        foreach (['cart.items.store', 'cart.items.update', 'cart.items.destroy', 'cart.clear'] as $name) {
            $route = app('router')->getRoutes()->getByName('storefront.development.'.$name);
            $middleware = $route->gatherMiddleware();

            $this->assertContains('web', $middleware);
            $this->assertContains('storefront.public', $middleware);
            $this->assertContains('storefront.cart', $middleware);
            $this->assertContains('throttle:public-cart', $middleware);
            $this->assertSame(10, $route->locksFor());
            $this->assertSame(10, $route->waitsFor());
        }
    }

    public function test_guest_cart_is_created_recovered_and_never_exposes_token_or_internal_ids(): void
    {
        [$store, $publication, $product] = $this->publicProduct('guest-cart');
        $variantKey = $this->variantKey($product['variant']->getKey());
        $response = $this->post(route('storefront.development.cart.items.store', [
            $publication->public_handle, $product['listing']->storefront_slug,
        ]), $this->addPayload($variantKey, 2));

        $response->assertRedirect();
        $cart = Cart::query()->sole();
        $cookieName = app(PublicCartIdentity::class)->cookieName($publication->public_handle);
        $response->assertCookie($cookieName);
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === $cookieName);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/tienda/'.$publication->public_handle, $cookie->getPath());
        $this->assertNotSame($cart->token, $cookie->getValue());

        $this->withCookie($cookieName, $cart->token)
            ->get(route('storefront.development.cart.show', $publication->public_handle))
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Cart')
                ->where('store.cart.itemCount', 2)
                ->where('cart.itemCount', 2)
                ->where('cart.items.0.productName', $product['product']->name)
                ->missing('cart.id')
                ->missing('cart.token')
                ->missing('cart.items.0.id')
                ->missing('cart.items.0.product_id')
                ->missing('cart.items.0.variant_id'));
        $this->assertSame($store->getKey(), $cart->store_id);
        $this->assertSame(0, InventoryReservation::query()->count());
    }

    public function test_guest_can_update_remove_and_clear_cart_with_authoritative_counts(): void
    {
        [, $publication, $product] = $this->publicProduct('cart-actions');
        $this->post(route('storefront.development.cart.items.store', [
            $publication->public_handle, $product['listing']->storefront_slug,
        ]), $this->addPayload($this->variantKey($product['variant']->getKey()), 2));
        $cart = Cart::query()->sole();
        $cookie = app(PublicCartIdentity::class)->cookieName($publication->public_handle);
        $this->withCookie($cookie, $cart->token);
        $lineKey = hash_hmac('sha256', $cart->items()->sole()->getKey(), (string) config('app.key'));

        $this->patch(route('storefront.development.cart.items.update', [$publication->public_handle, $lineKey]), [
            'quantity' => 3, 'mutation_id' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->assertSame(3, $cart->items()->sole()->quantity);

        $this->delete(route('storefront.development.cart.items.destroy', [$publication->public_handle, $lineKey]), [
            'mutation_id' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->assertSame(0, $cart->items()->count());

        $this->post(route('storefront.development.cart.items.store', [
            $publication->public_handle, $product['listing']->storefront_slug,
        ]), $this->addPayload($this->variantKey($product['variant']->getKey()), 1));
        $this->delete(route('storefront.development.cart.clear', $publication->public_handle), [
            'mutation_id' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->assertSame(0, $cart->items()->count());
        $this->assertNull($cart->fresh()->currency);
    }

    public function test_invalid_unavailable_and_no_longer_eligible_variants_are_rejected(): void
    {
        [, $publication, $product] = $this->publicProduct('cart-validation', stock: 2);
        $route = route('storefront.development.cart.items.store', [
            $publication->public_handle, $product['listing']->storefront_slug,
        ]);
        $key = $this->variantKey($product['variant']->getKey());

        $this->post($route, $this->addPayload($key, 0))->assertSessionHasErrors('quantity');
        $this->post($route, $this->addPayload($key, 3))->assertSessionHasErrors('cart');
        $this->assertSame(0, Cart::query()->count());

        $product['listing']->update(['status' => StoreListingStatus::Unpublished]);
        $this->post($route, $this->addPayload($key, 1))->assertNotFound();
    }

    public function test_prices_promotions_taxes_and_inventory_are_recalculated_by_cart_calculator(): void
    {
        [$store, $publication, $product] = $this->publicProduct('cart-money', price: '100.0000', stock: 5);
        $tenant = $store->tenant()->firstOrFail();
        $category = app(SaveTaxCategory::class)->create($tenant, [
            'code' => 'STANDARD', 'name' => 'Standard', 'status' => 'ACTIVE',
        ]);
        app(SaveTaxRate::class)->create($tenant, [
            'tax_category_id' => $category->getKey(), 'name' => 'IGV', 'rate' => '18.000000',
            'valid_from' => null, 'valid_until' => null, 'status' => 'ACTIVE',
        ]);
        $product['product']->update(['tax_category_id' => $category->getKey()]);
        app(CreatePromotion::class)->handle($tenant, [
            'name' => 'Public sale', 'internal_code' => 'PUBLIC-SALE', 'description' => null,
            'status' => 'ACTIVE', 'discount_type' => 'PERCENTAGE', 'discount_value' => '10',
            'currency' => null, 'starts_at' => null, 'ends_at' => null, 'priority' => 0,
            'scope' => 'ALL', 'product_ids' => [], 'category_ids' => [], 'variant_ids' => [],
        ]);

        $this->post(route('storefront.development.cart.items.store', [
            $publication->public_handle, $product['listing']->storefront_slug,
        ]), $this->addPayload($this->variantKey($product['variant']->getKey()), 2));
        $cart = Cart::query()->sole();
        $cookie = app(PublicCartIdentity::class)->cookieName($publication->public_handle);

        $this->withCookie($cookie, $cart->token)
            ->get(route('storefront.development.cart.show', $publication->public_handle))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cart.subtotalBeforeDiscounts', '200.0000')
                ->where('cart.discountTotal', '20.0000')
                ->where('cart.subtotal', '180.0000')
                ->where('cart.taxTotal', '27.4576')
                ->where('cart.valid', true)
                ->where('cart.items.0.available', 5));
    }

    public function test_expired_and_manipulated_tokens_cannot_recover_or_mutate_a_cart(): void
    {
        CarbonImmutable::setTestNow('2026-10-28 10:00:00');
        [, $publication, $product] = $this->publicProduct('expired-cart');
        $route = route('storefront.development.cart.items.store', [
            $publication->public_handle, $product['listing']->storefront_slug,
        ]);
        $this->post($route, $this->addPayload($this->variantKey($product['variant']->getKey()), 1));
        $old = Cart::query()->sole();
        $old->update(['expires_at' => now()]);
        $cookie = app(PublicCartIdentity::class)->cookieName($publication->public_handle);

        CarbonImmutable::setTestNow('2026-10-28 11:00:00');
        $this->withCookie($cookie, str_repeat('a', 64))
            ->get(route('storefront.development.cart.show', $publication->public_handle))
            ->assertInertia(fn (Assert $page) => $page->where('cart.itemCount', 0));
        $this->withCookie($cookie, $old->token)->post(
            $route,
            $this->addPayload($this->variantKey($product['variant']->getKey()), 1),
        )->assertRedirect();

        $this->assertSame(CartStatus::Expired, $old->fresh()->status);
        $this->assertSame(2, Cart::query()->count());
        CarbonImmutable::setTestNow();
    }

    public function test_two_stores_on_same_host_keep_distinct_cookies_and_never_cross_scope(): void
    {
        $tenant = $this->createTenant();
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        $publicationA = $this->publish($storeA, 'same-host-a');
        $publicationB = $this->publish($storeB, 'same-host-b');
        $productA = $this->createPublishedProduct($storeA, ['stock' => 5]);
        $productB = $this->createPublishedProduct($storeB, ['stock' => 5]);
        $identity = app(PublicCartIdentity::class);

        $this->post(route('storefront.development.cart.items.store', [
            $publicationA->public_handle, $productA['listing']->storefront_slug,
        ]), $this->addPayload($this->variantKey($productA['variant']->getKey()), 1));
        $cartA = Cart::query()->sole();
        $cookieA = $identity->cookieName($publicationA->public_handle);
        $cookieB = $identity->cookieName($publicationB->public_handle);
        $this->assertNotSame($cookieA, $cookieB);

        $this->withCookie($cookieB, $cartA->token)
            ->get(route('storefront.development.cart.show', $publicationB->public_handle))
            ->assertInertia(fn (Assert $page) => $page->where('cart.itemCount', 0));
        $this->post(route('storefront.development.cart.items.store', [
            $publicationB->public_handle, $productB['listing']->storefront_slug,
        ]), $this->addPayload($this->variantKey($productB['variant']->getKey()), 1));

        $this->assertSame(2, Cart::query()->count());
        $this->assertSame(1, Cart::query()->where('store_id', $storeA->getKey())->count());
        $this->assertSame(1, Cart::query()->where('store_id', $storeB->getKey())->count());
    }

    public function test_duplicate_mutation_id_is_idempotent_and_public_cart_is_rate_limited(): void
    {
        [, $publication, $product] = $this->publicProduct('idempotent-cart');
        $route = route('storefront.development.cart.items.store', [
            $publication->public_handle, $product['listing']->storefront_slug,
        ]);
        $payload = $this->addPayload($this->variantKey($product['variant']->getKey()), 2);

        $this->post($route, $payload)->assertRedirect();
        $this->post($route, $payload)->assertRedirect();
        $this->assertSame(2, Cart::query()->sole()->items()->sole()->quantity);

        foreach (range(1, 118) as $attempt) {
            $this->post($route, [])->assertSessionHasErrors();
        }
        $this->post($route, [])->assertTooManyRequests();
    }

    /** @return array{0: mixed, 1: mixed, 2: array<string, mixed>} */
    private function publicProduct(string $handle, string $price = '100.0000', int $stock = 10): array
    {
        $store = $this->createStore($this->createTenant());

        return [$store, $this->publish($store, $handle), $this->createPublishedProduct($store, compact('price', 'stock'))];
    }

    /** @return array<string, mixed> */
    private function addPayload(string $variantKey, int $quantity): array
    {
        return ['variant_key' => $variantKey, 'quantity' => $quantity, 'mutation_id' => (string) Str::uuid()];
    }

    private function variantKey(string $variantId): string
    {
        return hash_hmac('sha256', $variantId, (string) config('app.key'));
    }
}
