<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Application\CreateTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_unverified_user_cannot_access_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        $tenant = app(CreateTenant::class)->handle($user, 'Test Company');
        $store = Store::create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Test Store',
            'slug' => 'test-store',
            'status' => StoreStatus::Active,
            'currency' => 'PEN',
            'timezone' => 'America/Lima',
            'locale' => 'es_PE',
        ]);

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('auth.user.email', $user->email)
                ->where('currentTenant.id', $tenant->getKey())
                ->where('currentStore.id', $store->getKey()));
    }
}
