<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_is_available(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/ForgotPassword'));
    }

    public function test_reset_link_can_be_requested(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', __(Password::RESET_LINK_SENT));

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_response_does_not_reveal_unknown_accounts(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'unknown@example.com'])
            ->assertSessionHas('status', __(Password::RESET_LINK_SENT));

        Notification::assertNothingSent();
    }

    public function test_valid_token_resets_password_and_dispatches_event(): void
    {
        Notification::fake();
        Event::fake([PasswordResetEvent::class]);
        $user = User::factory()->create(['password' => 'old-password']);
        $token = '';

        Password::sendResetLink(['email' => $user->email]);
        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        $this->post('/reset-password', [
            'token' => $token,
            'email' => strtoupper($user->email),
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        Event::assertDispatched(PasswordResetEvent::class);
    }

    public function test_invalid_token_does_not_change_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_invalid_reset_does_not_reveal_whether_account_exists(): void
    {
        $knownUser = User::factory()->create();

        $this->from('/reset-password/invalid-token')->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => $knownUser->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors(['email' => __(Password::INVALID_TOKEN)]);

        $this->from('/reset-password/invalid-token')->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => 'unknown@example.com',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors(['email' => __(Password::INVALID_TOKEN)]);
    }
}
