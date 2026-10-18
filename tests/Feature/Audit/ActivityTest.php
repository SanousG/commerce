<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

class ActivityTest extends AuditFeatureTestCase
{
    public function test_owner_and_admin_can_view_activity_but_member_cannot(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $this->createMembership($admin, $tenant, SystemRole::Admin);
        $this->createMembership($member, $tenant, SystemRole::Member);
        $store = $this->storeFor($tenant);
        $session = $this->tenantSession($tenant) + [StoreContext::SESSION_KEY => $store->getKey()];

        foreach ([$owner, $admin] as $authorized) {
            $this->actingAs($authorized)->withSession($session)
                ->get(route('admin.activity.index'))->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('admin/activity/Index'));
        }

        $this->actingAs($member)->withSession($session)
            ->get(route('admin.activity.index'))->assertForbidden();
    }

    public function test_activity_filters_tenant_store_action_actor_and_date_without_leaking_foreign_entries(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $otherActor = User::factory()->create();
        $this->createMembership($otherActor, $tenant, SystemRole::Admin);
        $storeA = $this->storeFor($tenant, ['name' => 'Alpha']);
        $storeB = $this->storeFor($tenant, ['name' => 'Beta']);
        $otherTenant = $this->createTenant();
        $otherOwner = $this->ownerIn($otherTenant);
        $otherStore = $this->storeFor($otherTenant);

        $wanted = $this->audit($tenant, AuditAction::StoreUpdated, AuditSubjectType::Store, $owner, $storeA,
            Carbon::parse('2026-09-15 12:00:00'), subjectId: $storeA->getKey());
        $this->audit($tenant, AuditAction::StoreUpdated, AuditSubjectType::Store, $otherActor, $storeA,
            Carbon::parse('2026-09-15 13:00:00'), subjectId: $storeA->getKey());
        $this->audit($tenant, AuditAction::TenantUpdated, actor: $owner,
            occurredAt: Carbon::parse('2026-09-15 14:00:00'));
        $this->audit($tenant, AuditAction::StoreUpdated, AuditSubjectType::Store, $owner, $storeB,
            Carbon::parse('2026-09-15 15:00:00'), subjectId: $storeB->getKey());
        $this->audit($tenant, AuditAction::StoreUpdated, AuditSubjectType::Store, $owner, $storeA,
            Carbon::parse('2026-09-14 23:59:59'), subjectId: $storeA->getKey());
        $this->audit($otherTenant, AuditAction::StoreUpdated, AuditSubjectType::Store, $otherOwner, $otherStore,
            Carbon::parse('2026-09-15 12:00:00'), subjectId: $otherStore->getKey());

        $query = [
            'store_id' => $storeA->getKey(),
            'action' => AuditAction::StoreUpdated->value,
            'actor_id' => (string) $owner->getKey(),
            'date_from' => '2026-09-15',
            'date_to' => '2026-09-15',
        ];
        $this->actingAs($owner)->withSession($this->tenantSession($tenant) + [StoreContext::SESSION_KEY => $storeA->getKey()])
            ->get(route('admin.activity.index', $query))
            ->assertInertia(fn (Assert $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.id', $wanted->getKey())
                ->where('activities.data.0.store.id', $storeA->getKey())
                ->where('activities.data.0.actor.id', (string) $owner->getKey())
                ->where('filters.store_id', $query['store_id'])
                ->where('filters.action', $query['action'])
                ->where('filters.actor_id', $query['actor_id'])
                ->where('filters.date_from', $query['date_from'])
                ->where('filters.date_to', $query['date_to']));
    }

    public function test_current_store_all_store_and_system_actor_contracts_are_explicit(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $store = $this->storeFor($tenant);
        $storeA = $this->storeFor($tenant);
        $storeB = $this->storeFor($tenant);
        $current = $this->audit($tenant, AuditAction::StoreUpdated, AuditSubjectType::Store, $owner, $storeA);
        $this->audit($tenant, AuditAction::StoreUpdated, AuditSubjectType::Store, $owner, $storeB);
        $system = $this->audit($tenant, AuditAction::TenantUpdated);

        $session = $this->tenantSession($tenant) + [StoreContext::SESSION_KEY => $storeA->getKey()];
        $this->actingAs($owner)->withSession($session)
            ->get(route('admin.activity.index', ['store_id' => 'current']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.id', $current->getKey()));

        $this->actingAs($owner)->withSession($session)
            ->get(route('admin.activity.index', ['store_id' => 'all', 'actor_id' => 'system']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.id', $system->getKey())
                ->where('activities.data.0.actor', null));
    }

    public function test_activity_paginates_exactly_fifty_entries_and_preserves_query_string(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $store = $this->storeFor($tenant);
        foreach (range(1, 51) as $index) {
            $this->audit($tenant, AuditAction::TenantUpdated, actor: $owner,
                occurredAt: Carbon::parse('2026-09-01')->addMinutes($index));
        }

        $this->actingAs($owner)->withSession($this->tenantSession($tenant) + [StoreContext::SESSION_KEY => $store->getKey()])
            ->get(route('admin.activity.index', ['action' => AuditAction::TenantUpdated->value]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('activities.data', 50)
                ->where('activities.per_page', 50)
                ->where('activities.current_page', 1)
                ->where('activities.total', 51)
                ->where('activities.last_page', 2)
                ->where('activities.next_page_url', fn ($url) => is_string($url)
                    && str_contains($url, 'page=2')
                    && str_contains($url, 'action=tenant.updated')));
    }

    public function test_cross_tenant_owner_cannot_filter_by_foreign_resources_or_infer_entries(): void
    {
        $tenantA = $this->createTenant();
        $ownerA = $this->ownerIn($tenantA);
        $storeA = $this->storeFor($tenantA);
        $tenantB = $this->createTenant();
        $ownerB = $this->ownerIn($tenantB);
        $storeB = $this->storeFor($tenantB);
        $this->audit($tenantB, AuditAction::StoreUpdated, AuditSubjectType::Store, $ownerB, $storeB);

        $this->actingAs($ownerA)->withSession([
            TenantContext::SESSION_KEY => $tenantA->getKey(),
            StoreContext::SESSION_KEY => $storeA->getKey(),
        ])
            ->get(route('admin.activity.index'))
            ->assertInertia(fn (Assert $page) => $page->has('activities.data', 0)->has('stores', 1)->has('actors', 1));
        $this->get(route('admin.activity.index', ['store_id' => $storeB->getKey()]))
            ->assertSessionHasErrors('store_id');
        $this->get(route('admin.activity.index', ['actor_id' => (string) $ownerB->getKey()]))
            ->assertSessionHasErrors('actor_id');
    }
}
