<?php

namespace App\Http\Middleware;

use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Notifications\Application\UnreadNotificationCount;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly PermissionChecker $permissionChecker,
        private readonly UnreadNotificationCount $unreadNotificationCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user()?->only([
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                ]),
            ],
            'currentTenant' => fn () => $this->tenantContext->get()?->only([
                'id',
                'name',
                'slug',
            ]),
            'currentTenantMembership' => function () use ($request): ?array {
                if (! $request->user() || ! $this->tenantContext->hasTenant()) {
                    return null;
                }

                $membership = $this->permissionChecker->currentMembership($request->user());

                return $membership ? [
                    'role' => $membership->role->key,
                    'status' => $membership->status->value,
                    'permissions' => $membership->role->permissions->pluck('key'),
                ] : null;
            },
            'currentStore' => fn () => $this->storeContext->get()?->only([
                'id',
                'name',
                'slug',
                'currency',
                'timezone',
            ]),
            'unreadNotificationsCount' => fn (): int => $request->user() && $this->tenantContext->hasTenant()
                ? $this->unreadNotificationCount->forUser(
                    $this->tenantContext->id(),
                    $this->storeContext->id(),
                    $request->user(),
                    $this->permissionChecker,
                )
                : 0,
            'status' => fn () => $request->session()->get('status'),
        ];
    }
}
