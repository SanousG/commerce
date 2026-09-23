<?php

namespace App\Modules\Access\Providers;

use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Access\Domain\Enums\PermissionKey;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PermissionChecker::class);
    }

    public function boot(): void
    {
        foreach (PermissionKey::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn ($user): bool => app(PermissionChecker::class)->userCan($user, $permission),
            );
        }
    }
}
