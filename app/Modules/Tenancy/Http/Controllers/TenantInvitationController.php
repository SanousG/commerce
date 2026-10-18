<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Application\InviteTenantMember;
use App\Modules\Tenancy\Application\ResendTenantInvitation;
use App\Modules\Tenancy\Application\RevokeTenantInvitation;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Http\Requests\InviteTenantMemberRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantInvitationController extends Controller
{
    public function store(
        InviteTenantMemberRequest $request,
        TenantContext $tenantContext,
        InviteTenantMember $invite,
    ): RedirectResponse {
        $invite->handle(
            $tenantContext->get(),
            $request->user(),
            $request->validated('email'),
            $request->validated('role_id'),
        );

        return back()->with('status', 'Invitation sent.');
    }

    public function resend(
        Request $request,
        TenantContext $tenantContext,
        ResendTenantInvitation $resend,
        string $invitation,
    ): RedirectResponse {
        $resend->handle($tenantContext->get(), $invitation, $request->user());

        return back()->with('status', 'Invitation resent with a new secure link.');
    }

    public function revoke(
        Request $request,
        TenantContext $tenantContext,
        RevokeTenantInvitation $revoke,
        string $invitation,
    ): RedirectResponse {
        $revoke->handle($tenantContext->get(), $invitation, $request->user());

        return back()->with('status', 'Invitation revoked.');
    }
}
