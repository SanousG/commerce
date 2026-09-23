<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_is_available(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/Register'));
    }

    public function test_user_can_register_and_is_authenticated(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => '  Ada Lovelace  ',
            'email' => '  ADA@EXAMPLE.COM ',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ]);

        $user = User::query()->sole();

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_duplicate_email_is_rejected_after_normalization(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->post('/register', [
            'name' => 'Another Ada',
            'email' => 'ADA@EXAMPLE.COM',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->post('/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
    }
}
