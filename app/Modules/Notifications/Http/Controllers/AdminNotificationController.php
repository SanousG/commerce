<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Notifications\Domain\Models\AdminNotification;
use App\Modules\Notifications\Http\Requests\NotificationIndexRequest;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminNotificationController extends Controller
{
    public function index(NotificationIndexRequest $request, TenantContext $tenants, StoreContext $stores): Response
    {
        $validated = $request->validated();
        $unread = filter_var($validated['unread'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $active = filter_var($validated['active'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $resolved = filter_var($validated['resolved'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $notifications = $this->visibleQuery($request, $tenants, $stores)
            ->when($unread, fn ($query) => $query->whereNull('read_at'))
            ->when($active, fn ($query) => $query->whereNull('dismissed_at')->whereNull('resolved_at'))
            ->when($resolved, fn ($query) => $query->whereNotNull('resolved_at'))
            ->when(isset($validated['severity']), fn ($query) => $query->where('severity', $validated['severity']))
            ->when(isset($validated['type']), fn ($query) => $query->where('type', $validated['type']))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $notifications->through(fn (AdminNotification $notification): array => [
            'id' => $notification->getKey(),
            'type' => $notification->type->value,
            'severity' => $notification->severity->value,
            'title' => $notification->title,
            'message' => $notification->message,
            'action_url' => $notification->action_url,
            'read_at' => $notification->read_at?->toIso8601String(),
            'resolved_at' => $notification->resolved_at?->toIso8601String(),
            'dismissed_at' => $notification->dismissed_at?->toIso8601String(),
            'created_at' => $notification->created_at->toIso8601String(),
        ]);

        return Inertia::render('admin/notifications/Index', [
            'notifications' => $notifications,
            'filters' => [
                'unread' => (bool) ($validated['unread'] ?? false),
                'active' => (bool) ($validated['active'] ?? false),
                'resolved' => (bool) ($validated['resolved'] ?? false),
                'severity' => $validated['severity'] ?? '',
                'type' => $validated['type'] ?? '',
            ],
            'types' => array_column(NotificationType::cases(), 'value'),
            'severities' => array_column(NotificationSeverity::cases(), 'value'),
        ]);
    }

    public function read(Request $request, TenantContext $tenants, StoreContext $stores, string $notification): RedirectResponse
    {
        $notification = $this->visibleQuery($request, $tenants, $stores)->findOrFail($notification);
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return back();
    }

    public function readAll(Request $request, TenantContext $tenants, StoreContext $stores): RedirectResponse
    {
        $this->visibleQuery($request, $tenants, $stores)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return back();
    }

    public function dismiss(Request $request, TenantContext $tenants, StoreContext $stores, string $notification): RedirectResponse
    {
        $this->visibleQuery($request, $tenants, $stores)->findOrFail($notification)
            ->forceFill(['dismissed_at' => now()])->save();

        return back();
    }

    private function visibleQuery(Request $request, TenantContext $tenants, StoreContext $stores): Builder
    {
        abort_unless($tenants->hasTenant(), 404);
        $storeId = $stores->id();

        return AdminNotification::query()
            ->where('tenant_id', $tenants->id())
            ->where('recipient_user_id', $request->user()->getKey())
            ->where(fn ($query) => $query->whereNull('store_id')->when($storeId !== null, fn ($query) => $query->orWhere('store_id', $storeId)));
    }
}
