<?php

namespace Tests\Feature\Localization;

use App\Http\Middleware\SetUserLocale;
use App\Models\User;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Catalog\CatalogFeatureTestCase;

class AdminLocalizationTest extends CatalogFeatureTestCase
{
    use RefreshDatabase;

    public function test_spanish_is_the_default_and_is_shared_with_inertia(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);

        $this->assertSame('es-PE', $user->fresh()->admin_locale);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('localization.locale', 'es-PE')
                ->where('localization.fallback', 'es-PE')
                ->where('localization.supported', ['es-PE', 'en']));
    }

    public function test_guest_authentication_pages_use_spanish_before_a_user_is_available(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('auth/Login')
                ->where('localization.locale', 'es-PE')
                ->where('localization.fallback', 'es-PE'));

        $this->post(route('login'), [
            'email' => 'unknown@example.com',
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors([
            'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ]);
    }

    public function test_missing_or_unsupported_user_preference_falls_back_to_spanish(): void
    {
        $request = Request::create('/');
        $user = new User;
        $user->admin_locale = null;
        $request->setUserResolver(fn (): User => $user);

        app(SetUserLocale::class)->handle($request, fn () => response('ok'));

        $this->assertSame('es-PE', app()->getLocale());
    }

    public function test_user_can_change_language_and_it_persists_across_logout_and_tenant_switches(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()])
            ->patch(route('admin.locale.update'), ['locale' => 'en'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Language preference saved.');

        $this->assertDatabaseHas('users', ['id' => $user->getKey(), 'admin_locale' => 'en']);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->actingAs($user->fresh())->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()]);
        $this->post(route('admin.tenants.select', $tenantB))->assertRedirect();
        $this->get(route('admin.products.index'))->assertInertia(
            fn (AssertableInertia $page) => $page->where('localization.locale', 'en')
        );
    }

    public function test_user_can_change_back_to_spanish_without_changing_store_context(): void
    {
        $user = User::factory()->create(['admin_locale' => 'en']);
        $tenant = $this->createTenantFor($user);
        $storeId = fake()->uuid();

        $this->actingAs($user)->withSession([
            TenantContext::SESSION_KEY => $tenant->getKey(),
            StoreContext::SESSION_KEY => $storeId,
        ])->patch(route('admin.locale.update'), ['locale' => 'es-PE'])
            ->assertRedirect()
            ->assertSessionHas(StoreContext::SESSION_KEY, $storeId)
            ->assertSessionHas('status', 'Preferencia de idioma guardada.');

        $this->assertSame('es-PE', $user->fresh()->admin_locale);

        $this->get(route('admin.products.index'))->assertInertia(
            fn (AssertableInertia $page) => $page->where('localization.locale', 'es-PE')
        );
    }

    public function test_language_update_is_authenticated_validated_and_isolated_between_users(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->patch(route('admin.locale.update'), ['locale' => 'en'])
            ->assertRedirect(route('login'));

        $this->actingAs($first)
            ->patch(route('admin.locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->patch(route('admin.locale.update'), ['locale' => 'en'])->assertRedirect();

        $this->assertSame('en', $first->fresh()->admin_locale);
        $this->assertSame('es-PE', $second->fresh()->admin_locale);
    }

    public function test_catalog_validation_and_notifications_follow_the_user_language(): void
    {
        $spanish = User::factory()->create();
        $spanishTenant = $this->createTenantFor($spanish);

        $this->actingAs($spanish)->withSession([TenantContext::SESSION_KEY => $spanishTenant->getKey()])
            ->post(route('admin.categories.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'El campo nombre es obligatorio.']);

        $this->post(route('admin.categories.store'), ['name' => 'Seguridad'])
            ->assertSessionHas('status', 'Categoría creada.');

        $english = User::factory()->create(['admin_locale' => 'en']);
        $englishTenant = $this->createTenantFor($english);

        $this->actingAs($english)->withSession([TenantContext::SESSION_KEY => $englishTenant->getKey()])
            ->post(route('admin.categories.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'The name field is required.']);
    }
}
