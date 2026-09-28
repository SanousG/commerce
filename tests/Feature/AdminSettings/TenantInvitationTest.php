<?php

namespace Tests\Feature\AdminSettings;

use App\Mail\TenantInvitationMail;
use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Tenancy\Domain\Enums\TenantInvitationStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantInvitation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TenantInvitationTest extends AdminSettingsFeatureTestCase
{
    public function test_owner_invites_with_hashed_token_and_mail_link(): void
    {
        Mail::fake();
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $role = $this->role(SystemRole::Member);

        $this->actingAs($owner)->withSession($this->tenantSession($tenant))
            ->post(route('admin.settings.team.invitations.store'), ['email' => ' NEW@example.COM ', 'role_id' => $role->getKey()])
            ->assertRedirect()->assertSessionHasNoErrors();

        $invitation = TenantInvitation::sole();
        $this->assertSame(TenantInvitationStatus::Pending, $invitation->status);
        $this->assertSame('new@example.com', $invitation->email);
        $this->assertSame($tenant->getKey(), $invitation->tenant_id);
        $this->assertSame($role->getKey(), $invitation->role_id);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $invitation->token_hash);
        $rawToken = null;
        Mail::assertSent(TenantInvitationMail::class, function (TenantInvitationMail $mail) use (&$rawToken): bool {
            preg_match('#/invitations/([a-f0-9]{64})#', $mail->render(), $matches);
            $rawToken = $matches[1] ?? null;

            return $rawToken !== null;
        });
        $this->assertNotSame($rawToken, $invitation->token_hash);
        $this->assertSame($invitation->token_hash, hash('sha256', $rawToken));
    }

    public function test_duplicate_pending_invitation_is_rejected(): void
    {
        Mail::fake();
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $payload = ['email' => 'person@example.com', 'role_id' => $this->role(SystemRole::Member)->getKey()];

        $this->actingAs($owner)->withSession($this->tenantSession($tenant));
        $this->post(route('admin.settings.team.invitations.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('admin.settings.team.invitations.store'), $payload)->assertSessionHasErrors('email');
        $this->assertDatabaseCount('tenant_invitations', 1);
    }

    public function test_database_rejects_a_second_pending_invitation_for_same_tenant_and_email(): void
    {
        [, , $invitation] = $this->createInvitation();

        $this->expectException(QueryException::class);
        TenantInvitation::create([
            'tenant_id' => $invitation->tenant_id,
            'email' => $invitation->email,
            'role_id' => $invitation->role_id,
            'token_hash' => hash('sha256', Str::random(64)),
            'status' => TenantInvitationStatus::Pending,
            'invited_by_user_id' => $invitation->invited_by_user_id,
            'expires_at' => now()->addWeek(),
        ]);
    }

    public function test_invitation_endpoint_is_rate_limited_per_tenant_and_actor(): void
    {
        Mail::fake();
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $this->actingAs($owner)->withSession($this->tenantSession($tenant));

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->post(route('admin.settings.team.invitations.store'), [
                'email' => "invite-{$attempt}@example.com",
                'role_id' => $this->role(SystemRole::Member)->getKey(),
            ])->assertRedirect();
        }

        $this->post(route('admin.settings.team.invitations.store'), [
            'email' => 'blocked@example.com',
            'role_id' => $this->role(SystemRole::Member)->getKey(),
        ])->assertTooManyRequests();
    }

    public function test_resend_invalidates_old_token_and_revoke_invalidates_new_token(): void
    {
        Mail::fake();
        [$tenant, $owner, $invitation, $oldToken] = $this->createInvitation();

        $this->actingAs($owner)->withSession($this->tenantSession($tenant))
            ->post(route('admin.settings.team.invitations.resend', $invitation))->assertSessionHasNoErrors();

        $invitation->refresh();
        $this->assertNotSame(hash('sha256', $oldToken), $invitation->token_hash);
        $this->get(route('invitations.show', $oldToken))->assertInertia(fn ($page) => $page->where('valid', false));

        $this->post(route('admin.settings.team.invitations.revoke', $invitation))->assertSessionHasNoErrors();
        $this->assertSame(TenantInvitationStatus::Revoked, $invitation->refresh()->status);
    }

    public function test_existing_verified_user_accepts_once_without_duplicate_user_or_membership(): void
    {
        [$tenant, , $invitation, $token] = $this->createInvitation('member@example.com');
        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($user)->post(route('invitations.accept', $token))->assertRedirect(route('admin.stores.index'));
        $this->post(route('invitations.accept', $token))->assertRedirect(route('admin.stores.index'));

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('tenant_members', 2);
        $this->assertDatabaseHas('tenant_members', ['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey()]);
        $this->assertSame(TenantInvitationStatus::Accepted, $invitation->refresh()->status);
    }

    public function test_wrong_email_expired_and_revoked_invitations_cannot_be_accepted(): void
    {
        [$tenant, , $invitation, $token] = $this->createInvitation('target@example.com');
        $wrong = User::factory()->create(['email' => 'wrong@example.com']);

        $this->actingAs($wrong)->post(route('invitations.accept', $token))->assertSessionHasErrors('invitation');
        $this->assertDatabaseMissing('tenant_members', ['tenant_id' => $tenant->getKey(), 'user_id' => $wrong->getKey()]);

        $invitation->update(['expires_at' => now()->subMinute()]);
        $target = User::factory()->create(['email' => 'target@example.com']);
        $this->actingAs($target)->post(route('invitations.accept', $token))->assertSessionHasErrors('invitation');
        $this->assertSame(TenantInvitationStatus::Expired, $invitation->refresh()->status);
    }

    public function test_revoked_token_cannot_be_accepted(): void
    {
        [$tenant, , $invitation, $token] = $this->createInvitation('revoked@example.com');
        $invitation->update(['status' => TenantInvitationStatus::Revoked]);
        $user = User::factory()->create(['email' => 'revoked@example.com']);

        $this->actingAs($user)->post(route('invitations.accept', $token))->assertSessionHasErrors('invitation');
        $this->assertDatabaseMissing('tenant_members', ['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey()]);
    }

    public function test_guest_invitation_preserves_intended_url_for_login_or_registration(): void
    {
        [, , , $token] = $this->createInvitation();

        $this->get(route('invitations.show', $token))
            ->assertOk()
            ->assertSessionHas('url.intended', route('invitations.show', $token));
    }

    public function test_privileged_user_cannot_resend_another_tenants_invitation(): void
    {
        Mail::fake();
        [$tenantA, $ownerA] = [$this->createTenant(), null];
        $ownerA = $this->ownerIn($tenantA);
        [, , $invitationB] = $this->createInvitation('other@example.com');

        $this->actingAs($ownerA)->withSession($this->tenantSession($tenantA))
            ->post(route('admin.settings.team.invitations.resend', $invitationB))->assertNotFound();
    }

    /** @return array{0: Tenant, 1: User, 2: TenantInvitation, 3: string} */
    private function createInvitation(string $email = 'invitee@example.com'): array
    {
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $token = bin2hex(random_bytes(32));
        $invitation = TenantInvitation::create([
            'tenant_id' => $tenant->getKey(), 'email' => $email,
            'role_id' => $this->role(SystemRole::Member)->getKey(), 'token_hash' => hash('sha256', $token),
            'status' => TenantInvitationStatus::Pending, 'invited_by_user_id' => $owner->getKey(),
            'expires_at' => now()->addWeek(),
        ]);

        return [$tenant, $owner, $invitation, $token];
    }
}
