<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('password-email', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('verification', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(6)->by((string) $key);
        });

        RateLimiter::for('sunat-submit', function (Request $request) {
            $user = $request->user();

            return [
                Limit::perMinute(10)->by('sunat-submit|'.($user?->getAuthIdentifier() ?? $request->ip())),
                Limit::perMinute(30)->by('sunat-submit|'.$request->ip()),
            ];
        });

        RateLimiter::for('tenant-invitations', function (Request $request) {
            $tenant = $request->session()->get('current_tenant_id', 'none');
            $user = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return [
                Limit::perMinute(10)->by("tenant-invitations|{$tenant}|{$user}"),
                Limit::perHour(50)->by("tenant-invitations|{$tenant}"),
            ];
        });

        RateLimiter::for('invitation-accept', fn (Request $request) => Limit::perMinute(10)
            ->by('invitation-accept|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
