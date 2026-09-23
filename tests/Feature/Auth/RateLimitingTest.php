<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => 'limited@example.com',
                'password' => 'invalid-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post('/login', [
            'email' => 'limited@example.com',
            'password' => 'invalid-password',
        ])->assertTooManyRequests();
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        Notification::fake();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/forgot-password', ['email' => 'limited@example.com'])
                ->assertSessionHasNoErrors();
        }

        $this->post('/forgot-password', ['email' => 'limited@example.com'])
            ->assertTooManyRequests();
    }

    public function test_verification_resend_is_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($user)
                ->post('/email/verification-notification')
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($user)
            ->post('/email/verification-notification')
            ->assertTooManyRequests();
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/register', [
                'name' => 'Rate Limited',
                'email' => "limited{$attempt}@example.com",
                'password' => 'correct-horse-battery-staple',
                'password_confirmation' => 'correct-horse-battery-staple',
            ]);
        }

        $this->post('/register', [
            'name' => 'Rate Limited',
            'email' => 'blocked@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])->assertTooManyRequests();
    }

    public function test_password_reset_submission_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post('/reset-password', [
                'token' => 'invalid-token',
                'email' => "limited{$attempt}@example.com",
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => 'blocked@example.com',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertTooManyRequests();
    }
}
