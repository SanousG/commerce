<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Tenancy\Application\CreateTenant;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_view_tenant_creation_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.tenants.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/tenants/Create'));
    }

    public function test_verified_user_can_create_tenant_as_owner_and_make_it_active(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.tenants.store'), ['name' => 'Northwind Traders'])
            ->assertRedirect(route('admin.dashboard'));

        $tenant = Tenant::query()->sole();

        $this->assertStringStartsWith('northwind-traders-', $tenant->slug);
        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertDatabaseHas('tenant_members', [
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => Role::query()->system(SystemRole::Owner)->sole()->getKey(),
            'status' => TenantMemberStatus::Active->value,
        ]);
        $this->assertSame(SystemRole::Owner->value, $user->fresh()->tenantMemberships()->sole()->role->key);
        $this->assertNotNull($user->fresh()->tenantMemberships()->sole()->joined_at);
        $this->assertTrue($user->fresh()->tenants()->whereKey($tenant->getKey())->exists());
        $this->assertTrue($tenant->users()->whereKey($user->getKey())->exists());
        $this->assertSame($tenant->getKey(), session(TenantContext::SESSION_KEY));
    }

    public function test_tenant_and_membership_creation_is_atomic(): void
    {
        $unsavedUser = User::factory()->make();

        try {
            app(CreateTenant::class)->handle($unsavedUser, 'Must Roll Back');
            $this->fail('The membership insert should have failed.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('tenants', ['name' => 'Must Roll Back']);
        }
    }

    public function test_unverified_user_cannot_create_tenant(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('admin.tenants.store'), ['name' => 'Forbidden Company'])
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseMissing('tenants', ['name' => 'Forbidden Company']);
    }
}
