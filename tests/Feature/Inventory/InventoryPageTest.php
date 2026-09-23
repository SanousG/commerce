<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Tenancy\Application\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

class InventoryPageTest extends InventoryFeatureTestCase
{
    public function test_index_is_tenant_scoped_searchable_and_paginated_with_aggregates(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $other = $this->createTenantFor(User::factory()->create());
        $location = $tenant->defaultInventoryLocation()->sole();
        $needle = $this->createVariant($tenant, 'NEEDLE-SKU', 'Findable product');
        $this->stock($tenant, $location, $needle, 9, $user);
        foreach (range(1, 20) as $index) {
            $this->createVariant($tenant, sprintf('PAGE-%02d', $index));
        }
        $this->createVariant($other, 'SECRET-SKU', 'Secret product');

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.inventory.index', ['search' => 'Findable']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/inventory/Index')
                ->has('variants.data', 1)
                ->where('variants.data.0.sku', 'NEEDLE-SKU')
                ->where('variants.data.0.on_hand', 9)
                ->where('variants.data.0.available', 9)
                ->where('filters.search', 'Findable')
                ->has('variants.links'));

        $this->get(route('admin.inventory.index', ['search' => 'SECRET-SKU']))
            ->assertInertia(fn (Assert $page) => $page->has('variants.data', 0));
        $this->get(route('admin.inventory.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('variants.data', 20)
                ->where('variants.last_page', 2));
    }

    public function test_detail_is_tenant_scoped_and_exposes_levels_movements_and_active_locations(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $default = $tenant->defaultInventoryLocation()->sole();
        $second = $this->createLocation($tenant, ['name' => 'Alpha depot', 'code' => 'ALPHA']);
        $this->createLocation($tenant, ['name' => 'Inactive', 'code' => 'OFF', 'status' => 'INACTIVE']);
        $variant = $this->createVariant($tenant, 'DETAIL-SKU', 'Detailed product');
        $this->stock($tenant, $default, $variant, 4, $user);
        $this->stock($tenant, $second, $variant, 2, $user);
        $foreign = $this->createVariant($this->createTenantFor(User::factory()->create()));

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.inventory.variants.show', $variant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/inventory/Variant')
                ->where('product.name', 'Detailed product')
                ->where('variant.sku', 'DETAIL-SKU')
                ->has('levels', 2)
                ->where('levels.0.location_name', 'Almacén principal')
                ->where('levels.1.location_name', 'Alpha depot')
                ->has('movements.data', 2)
                ->has('movements.links')
                ->has('locations', 2));

        $this->get(route('admin.inventory.variants.show', $foreign))->assertNotFound();
    }

    public function test_switching_tenant_changes_inventory_index_and_detail_visibility(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);
        $variantA = $this->createVariant($tenantA, 'TENANT-A');
        $variantB = $this->createVariant($tenantB, 'TENANT-B');
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()]);

        $this->get(route('admin.inventory.index'))->assertInertia(fn (Assert $page) => $page
            ->where('variants.data.0.sku', 'TENANT-A'));
        $this->get(route('admin.inventory.variants.show', $variantB))->assertNotFound();
        $this->post(route('admin.tenants.select', $tenantB))->assertRedirect();
        $this->get(route('admin.inventory.index'))->assertInertia(fn (Assert $page) => $page
            ->where('variants.data.0.sku', 'TENANT-B'));
        $this->get(route('admin.inventory.variants.show', $variantA))->assertNotFound();
        $this->get(route('admin.inventory.variants.show', $variantB))->assertOk();
    }

    public function test_member_can_view_inventory_but_cannot_adjust_or_transfer(): void
    {
        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        $location = $tenant->defaultInventoryLocation()->sole();
        $variant = $this->createVariant($tenant);
        $destination = $this->createLocation($tenant);
        $this->actingAs($member)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->get(route('admin.inventory.index'))->assertOk();
        $this->get(route('admin.inventory.variants.show', $variant))->assertOk();
        $this->post(route('admin.inventory.adjust', [$location, $variant]), [
            'quantity_delta' => 1, 'reason' => 'Denied',
        ])->assertForbidden();
        $this->get(route('admin.inventory.transfers.create'))->assertForbidden();
        $this->post(route('admin.inventory.transfers.store'), [
            'source_location_id' => $location->getKey(),
            'destination_location_id' => $destination->getKey(),
            'lines' => [['product_variant_id' => $variant->getKey(), 'quantity' => 1]],
        ])->assertForbidden();
    }
}
