<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\AcceptTenantInvitation;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Enums\TenantInvitationStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\TenantInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcceptTenantInvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = TenantInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->with(['tenant:id,name,status', 'role:id,name,key,is_system'])
            ->first();

        if ($invitation?->status === TenantInvitationStatus::Pending && $invitation->expires_at->isPast()) {
            $invitation->update(['status' => TenantInvitationStatus::Expired]);
        }

        $valid = $invitation !== null
            && $invitation->status === TenantInvitationStatus::Pending
            && $invitation->tenant->status === TenantStatus::Active
            && $invitation->role->is_system;

        if (! $request->user() && $valid) {
            $request->session()->put('url.intended', route('invitations.show', ['token' => $token]));
        }

        return Inertia::render('invitations/Show', [
            'token' => $valid ? $token : null,
            'valid' => $valid,
            'invitation' => $valid ? [
                'tenant' => $invitation->tenant->name,
                'role' => $invitation->role->name,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'email_matches' => ! $request->user() || strtolower(trim($request->user()->email)) === $invitation->email,
            ] : null,
        ]);
    }

    public function accept(
        Request $request,
        AcceptTenantInvitation $acceptInvitation,
        string $token,
    ): RedirectResponse {
        $membership = $acceptInvitation->handle($token, $request->user());
        $request->session()->put(TenantContext::SESSION_KEY, $membership->tenant_id);
        $request->session()->forget(StoreContext::SESSION_KEY);

        return to_route('admin.stores.index')->with('status', 'Invitation accepted.');
    }
}
