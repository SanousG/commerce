<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
    }

    public function test_verified_user_can_log_in_and_is_sent_to_tenant_onboarding(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => ' ADA@EXAMPLE.COM ',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get('/admin/dashboard')->assertRedirect(route('admin.tenants.create'));
    }

    public function test_login_regenerates_the_session_identifier(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $this->startSession();
        $previousSessionId = session()->getId();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertNotSame($previousSessionId, session()->getId());
    }

    public function test_unverified_user_is_sent_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create(['password' => 'password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('verification.notice'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_remember_me_sets_the_recaller_cookie(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ])->assertCookie(Auth::guard('web')->getRecallerName());
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
