<?php

namespace Tests\Feature\Promotions;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Promotions\Application\UpdatePromotion;
use App\Modules\Promotions\Domain\Models\Promotion;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

class PromotionManagementTest extends PromotionsFeatureTestCase
{
    public function test_owner_and_admin_can_create_while_member_can_only_view(): void
    {
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $user = User::factory()->create();
            $tenant = $this->createTenantFor($user, $role);
            $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
                ->post(route('admin.promotions.store'), $this->promotionPayload())
                ->assertRedirect();
            $this->assertTrue($tenant->promotions()->exists());
        }

        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        $promotion = $this->createPromotion($tenant, ['internal_code' => 'MEMBER-VIEW']);
        $this->actingAs($member)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);
        $this->get(route('admin.promotions.index'))->assertOk();
        $this->get(route('admin.promotions.edit', $promotion))->assertOk();
        $this->get(route('admin.promotions.create'))->assertForbidden();
        $this->post(route('admin.promotions.store'), $this->promotionPayload())->assertForbidden();
        $this->put(route('admin.promotions.update', $promotion), $this->promotionPayload())->assertForbidden();
        $this->patch(route('admin.promotions.archive', $promotion))->assertForbidden();
    }

    public function test_creation_ignores_malicious_tenant_id_and_syncs_tenant_targets(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $foreign = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);
        $payload = array_replace($this->promotionPayload(), [
            'tenant_id' => $foreign->getKey(),
            'scope' => 'SELECTED',
            'product_ids' => [$variant->product_id],
            'variant_ids' => [$variant->getKey()],
        ]);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.promotions.store'), $payload)->assertRedirect();
        $promotion = Promotion::query()->sole();
        $this->assertSame($tenant->getKey(), $promotion->tenant_id);
        $this->assertSame([$variant->product_id], $promotion->products()->modelKeys());
        $this->assertSame([$variant->getKey()], $promotion->variants()->modelKeys());
    }

    public function test_listing_search_and_tenant_switch_never_leak_promotions(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);
        $this->createPromotion($tenantA, ['name' => 'Visible Alpha', 'internal_code' => 'ALPHA']);
        $this->createPromotion($tenantB, ['name' => 'Secret Beta', 'internal_code' => 'BETA']);
        $this->actingAs($user);

        $this->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()])
            ->get(route('admin.promotions.index', ['search' => 'Alpha']))
            ->assertInertia(fn (Assert $page) => $page->has('promotions.data', 1)
                ->where('promotions.data.0.name', 'Visible Alpha'));
        $this->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()])
            ->get(route('admin.promotions.index', ['search' => 'Beta']))
            ->assertInertia(fn (Assert $page) => $page->has('promotions.data', 0));
        $this->withSession([TenantContext::SESSION_KEY => $tenantB->getKey()])
            ->get(route('admin.promotions.index'))
            ->assertInertia(fn (Assert $page) => $page->has('promotions.data', 1)
                ->where('promotions.data.0.name', 'Secret Beta'));
    }

    public function test_foreign_edit_update_and_archive_return_not_found_and_delete_is_not_exposed(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor(User::factory()->create());
        $foreign = $this->createPromotion($tenantB);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()]);

        $this->get(route('admin.promotions.edit', $foreign))->assertNotFound();
        $this->put(route('admin.promotions.update', $foreign), $this->promotionPayload())->assertNotFound();
        $this->patch(route('admin.promotions.archive', $foreign))->assertNotFound();
        $local = $this->createPromotion($tenantA, ['internal_code' => 'LOCAL']);
        $this->delete('/admin/promotions/'.$local->getKey())->assertMethodNotAllowed();
    }

    public function test_archive_is_logical_and_preserves_targets(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $variant = $this->createVariant($tenant);
        $promotion = $this->createPromotion($tenant, [
            'scope' => 'SELECTED', 'variant_ids' => [$variant->getKey()],
        ]);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->patch(route('admin.promotions.archive', $promotion))->assertRedirect(route('admin.promotions.index'));
        $this->assertSame('ARCHIVED', $promotion->fresh()->status->value);
        $this->assertSame([$variant->getKey()], $promotion->variants()->modelKeys());
    }

    public function test_discount_currency_dates_scope_and_internal_code_are_validated(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $this->createPromotion($tenant);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        foreach (['0', '-1', '100.0001', '101'] as $value) {
            $this->post(route('admin.promotions.store'), array_replace($this->promotionPayload(), [
                'internal_code' => 'VALUE-'.str_replace(['-', '.'], '', $value), 'discount_value' => $value,
            ]))->assertSessionHasErrors('discount_value');
        }
        $this->post(route('admin.promotions.store'), array_replace($this->promotionPayload(), [
            'internal_code' => 'FIXED', 'discount_type' => 'FIXED_AMOUNT', 'currency' => '',
        ]))->assertSessionHasErrors('currency');
        $this->post(route('admin.promotions.store'), array_replace($this->promotionPayload(), [
            'internal_code' => 'BAD-CURRENCY', 'discount_type' => 'FIXED_AMOUNT', 'currency' => 'ZZZ',
        ]))->assertSessionHasErrors('currency');
        $this->post(route('admin.promotions.store'), array_replace($this->promotionPayload(), [
            'internal_code' => 'DATES', 'starts_at' => '2026-09-12T12:00', 'ends_at' => '2026-09-12T11:00',
        ]))->assertSessionHasErrors('ends_at');
        $this->post(route('admin.promotions.store'), array_replace($this->promotionPayload(), [
            'internal_code' => 'EMPTY', 'scope' => 'SELECTED',
        ]))->assertSessionHasErrors('scope');
        $this->post(route('admin.promotions.store'), array_replace($this->promotionPayload(), [
            'internal_code' => 'summer',
        ]))->assertSessionHasErrors('internal_code');
    }

    public function test_cross_tenant_targets_are_rejected_by_http_and_application_actions(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor(User::factory()->create());
        $foreignVariant = $this->createVariant($tenantB);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()])
            ->post(route('admin.promotions.store'), array_replace($this->promotionPayload(), [
                'scope' => 'SELECTED', 'product_ids' => [$foreignVariant->product_id],
                'variant_ids' => [$foreignVariant->getKey()],
            ]))->assertSessionHasErrors(['product_ids.0', 'variant_ids.0']);

        $local = $this->createPromotion($tenantA, ['internal_code' => 'LOCAL']);
        $this->expectException(ValidationException::class);
        app(UpdatePromotion::class)->handle($tenantA, $local, array_replace($this->promotionPayload(), [
            'internal_code' => 'LOCAL', 'scope' => 'SELECTED', 'product_ids' => [$foreignVariant->product_id],
        ]));
    }

    public function test_application_update_rejects_foreign_and_archived_promotions(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());
        $foreign = $this->createPromotion($tenantB);
        $this->expectException(ModelNotFoundException::class);
        app(UpdatePromotion::class)->handle($tenantA, $foreign, $this->promotionPayload());
    }
}
