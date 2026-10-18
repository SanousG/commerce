<?php

namespace Tests\Feature\Audit;

use App\Mail\TenantInvitationMail;
use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Models\AuditEntry;
use App\Modules\Stores\Application\UpdateStore;
use App\Modules\Tenancy\Application\UpdateTenantSettings;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use Illuminate\Support\Facades\Mail;

class TeamAndSettingsAuditTest extends AuditFeatureTestCase
{
    public function test_invite_audit_has_practical_metadata_and_contains_no_raw_or_hashed_token(): void
    {
        Mail::fake();
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $role = $this->role(SystemRole::Member);
        $rawToken = null;

        $this->actingAs($owner)->withSession($this->tenantSession($tenant))
            ->post(route('admin.settings.team.invitations.store'), [
                'email' => ' Audit.Invite@example.com ',
                'role_id' => $role->getKey(),
            ])->assertSessionHasNoErrors();
        Mail::assertSent(TenantInvitationMail::class, function (TenantInvitationMail $mail) use (&$rawToken): bool {
            preg_match('#/invitations/([a-f0-9]{64})#', $mail->render(), $matches);
            $rawToken = $matches[1] ?? null;

            return $rawToken !== null;
        });

        $invitation = $tenant->invitations()->sole();
        $entry = AuditEntry::query()->where('action', AuditAction::TenantMemberInvited)->sole();
        $this->assertSame($tenant->getKey(), $entry->tenant_id);
        $this->assertSame($owner->getKey(), $entry->actor_user_id);
        $this->assertSame($invitation->getKey(), $entry->subject_id);
        $this->assertSame('audit.invite@example.com', $entry->subject_label);
        $this->assertSame(['role' => SystemRole::Member->value], $entry->metadata);
        $serialized = $entry->toJson();
        $this->assertStringNotContainsString($rawToken, $serialized);
        $this->assertStringNotContainsString($invitation->token_hash, $serialized);
    }

    public function test_role_deactivate_and_reactivate_each_record_changed_metadata_once_and_noops_do_not_append(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $memberUser = User::factory()->create();
        $member = $this->createMembership($memberUser, $tenant, SystemRole::Member);
        $this->actingAs($owner)->withSession($this->tenantSession($tenant));

        $this->patch(route('admin.settings.team.members.role', $member), [
            'role_id' => $this->role(SystemRole::Admin)->getKey(),
        ])->assertSessionHasNoErrors();
        $roleAudit = AuditEntry::query()->where('action', AuditAction::TenantMemberRoleChanged)->sole();
        $this->assertEquals(['from_role' => 'MEMBER', 'to_role' => 'ADMIN'], $roleAudit->metadata);

        $this->post(route('admin.settings.team.members.deactivate', $member))->assertSessionHasNoErrors();
        $this->post(route('admin.settings.team.members.deactivate', $member))->assertSessionHasNoErrors();
        $deactivated = AuditEntry::query()->where('action', AuditAction::TenantMemberDeactivated)->sole();
        $this->assertEquals([
            'changes' => ['status' => ['from' => 'ACTIVE', 'to' => 'SUSPENDED']],
        ], $deactivated->metadata);
        $this->assertSame(TenantMemberStatus::Suspended, $member->fresh()->status);

        $this->post(route('admin.settings.team.members.reactivate', $member))->assertSessionHasNoErrors();
        $this->post(route('admin.settings.team.members.reactivate', $member))->assertSessionHasNoErrors();
        $reactivated = AuditEntry::query()->where('action', AuditAction::TenantMemberReactivated)->sole();
        $this->assertEquals([
            'changes' => ['status' => ['from' => 'SUSPENDED', 'to' => 'ACTIVE']],
        ], $reactivated->metadata);
        $this->assertSame(TenantMemberStatus::Active, $member->fresh()->status);
    }

    public function test_cross_tenant_team_resource_mutations_are_not_found_and_create_no_audit(): void
    {
        $tenantA = $this->createTenant();
        $ownerA = $this->ownerIn($tenantA);
        $tenantB = $this->createTenant();
        $ownerB = $this->ownerIn($tenantB);
        $memberB = $ownerB->tenantMemberships()->where('tenant_id', $tenantB->getKey())->sole();

        $this->actingAs($ownerA)->withSession($this->tenantSession($tenantA))
            ->post(route('admin.settings.team.members.deactivate', $memberB))->assertNotFound();

        $this->assertSame(TenantMemberStatus::Active, $memberB->fresh()->status);
        $this->assertDatabaseCount('audit_entries', 0);
    }

    public function test_tenant_and_store_updates_audit_changed_fields_only_and_noops_do_not_append(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $store = $this->storeFor($tenant, ['name' => 'Original Store', 'currency' => 'PEN']);

        app(UpdateTenantSettings::class)->handle($tenant, 'Renamed Company', $owner);
        app(UpdateTenantSettings::class)->handle($tenant->fresh(), 'Renamed Company', $owner);
        $tenantAudit = AuditEntry::query()->where('action', AuditAction::TenantUpdated)->sole();
        $this->assertEquals([
            'changes' => ['name' => ['from' => $tenant->name, 'to' => 'Renamed Company']],
        ], $tenantAudit->metadata);

        $attributes = [
            'name' => 'Renamed Store',
            'status' => $store->status->value,
            'currency' => $store->currency,
            'timezone' => $store->timezone,
            'locale' => $store->locale,
        ];
        app(UpdateStore::class)->handle($tenant->fresh(), $store->getKey(), $attributes, $owner);
        app(UpdateStore::class)->handle($tenant->fresh(), $store->getKey(), $attributes, $owner);
        $storeAudit = AuditEntry::query()->where('action', AuditAction::StoreUpdated)->sole();
        $this->assertEquals([
            'changes' => ['name' => ['from' => 'Original Store', 'to' => 'Renamed Store']],
        ], $storeAudit->metadata);
        $this->assertSame($store->getKey(), $storeAudit->store_id);
        $this->assertDatabaseCount('audit_entries', 2);
    }
}
