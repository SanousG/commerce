<?php

namespace Tests\Feature\Cart;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Cart\Application\CreateCart;
use Inertia\Testing\AssertableInertia;

class CartHttpAndIsolationTest extends CartsFeatureTestCase
{
    public function test_owner_and_admin_manage_while_member_only_views(): void
    {
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $user = User::factory()->create();
            $tenant = $this->createTenantFor($user, $role);
            $store = $this->createStore($tenant);
            $this->useContext($user, $tenant, $store);
            $this->post(route('admin.carts.store'))->assertRedirect();
        }

        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        $store = $this->createStore($tenant);
        $this->useContext($member, $tenant, $store);
        $this->get(route('admin.carts.index'))->assertOk();
        $this->post(route('admin.carts.store'))->assertForbidden();
        $cart = app(CreateCart::class)->handle($store);
        $this->get(route('admin.carts.show', $cart))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canManage', false)->has('customers', 0)->has('listings', 0));
    }

    public function test_index_and_show_are_strictly_current_store_and_tenant_scoped(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        $cartA = app(CreateCart::class)->handle($storeA);
        $cartB = app(CreateCart::class)->handle($storeB);
        $foreignTenant = $this->createTenantFor(User::factory()->create());
        $foreignCart = app(CreateCart::class)->handle($this->createStore($foreignTenant));
        $this->useContext($user, $tenant, $storeA);

        $this->get(route('admin.carts.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/carts/Index')->has('carts.data', 1)->where('carts.data.0.id', $cartA->getKey()));
        $this->get(route('admin.carts.show', $cartB))->assertNotFound();
        $this->get(route('admin.carts.show', $foreignCart))->assertNotFound();
        $this->delete(route('admin.carts.items.clear', $cartB))->assertNotFound();
    }

    public function test_cross_store_and_cross_tenant_listings_return_not_found(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        $cart = app(CreateCart::class)->handle($storeA);
        [, $listingB, $variantB] = $this->sellableCart($storeB);
        $foreignTenant = $this->createTenantFor(User::factory()->create());
        [, $foreignListing, $foreignVariant] = $this->sellableCart($this->createStore($foreignTenant));
        $this->useContext($user, $tenant, $storeA);

        $this->post(route('admin.carts.items.store', $cart), [
            'store_listing_id' => $listingB->getKey(), 'product_variant_id' => $variantB->getKey(), 'quantity' => 1,
        ])->assertNotFound();
        $this->post(route('admin.carts.items.store', $cart), [
            'store_listing_id' => $foreignListing->getKey(), 'product_variant_id' => $foreignVariant->getKey(), 'quantity' => 1,
        ])->assertNotFound();
    }

    public function test_index_paginates_twenty_and_never_exposes_full_tokens(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        foreach (range(1, 21) as $_) {
            app(CreateCart::class)->handle($store);
        }
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.carts.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('carts.data', 20)->missing('carts.data.0.token')->has('carts.data.0.token_hint'));
    }
}
