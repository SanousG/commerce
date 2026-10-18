<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Application\NotificationPreferences;
use App\Modules\Notifications\Application\NotificationRouting;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Notifications\Http\Requests\UpdateNotificationPreferenceRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request, TenantContext $tenants, NotificationPreferences $preferences): Response
    {
        abort_unless($tenants->hasTenant(), 404);
        $preference = $preferences->forUser((string) $tenants->id(), (int) $request->user()->getKey());

        return Inertia::render('admin/settings/Notifications', [
            'preferences' => [
                'email_enabled' => $preference->email_enabled,
                'muted_types' => array_values($preference->muted_types ?? []),
            ],
            'types' => collect(NotificationType::cases())
                ->reject(fn (NotificationType $type): bool => $type === NotificationType::TeamInvitation)
                ->map(fn (NotificationType $type): string => $type->value)
                ->values(),
            'mandatoryTypes' => NotificationRouting::MANDATORY_TYPES,
        ]);
    }

    public function update(
        UpdateNotificationPreferenceRequest $request,
        TenantContext $tenants,
        NotificationPreferences $preferences,
    ): RedirectResponse {
        abort_unless($tenants->hasTenant(), 404);
        $preferences->update(
            (string) $tenants->id(),
            (int) $request->user()->getKey(),
            $request->validated(),
        );

        return back()->with('status', 'Notification preferences saved.');
    }
}
