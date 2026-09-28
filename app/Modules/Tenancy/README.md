# Tenancy administration

## Tenant and Store ownership

A Tenant is the company or organization. Company settings currently own the
company name and lifecycle status. A Store is an operational sales channel and
owns its currency, timezone, locale, payment settings, listings and fiscal
series. Team membership is Tenant-wide and never changes when the active Store
changes.

The active Tenant is always resolved from an authenticated, active membership.
Controllers and actions never accept a frontend `tenant_id` as authority.

## Team lifecycle

System roles remain `OWNER`, `ADMIN` and `MEMBER`; custom roles are not enabled.
`members.view` permits the Team page. `members.manage` permits invitations,
role changes and membership activation changes. In the current role matrix,
OWNER manages the team, ADMIN can view it, and MEMBER has no Team access.

Deactivation uses the existing `SUSPENDED` membership status and preserves the
historical membership. `ResolveTenant` rejects it on the next request and
clears stale Tenant and Store session context. Reactivation restores access
without creating another membership.

Users cannot change their own role or deactivate themselves. A Tenant must
always retain one active OWNER. Owner-changing actions lock the Tenant row
before locking memberships and checking the remaining active owners. This
serializes competing owner mutations for the same Tenant.

## Invitation lifecycle

Invitations transition from `PENDING` to `ACCEPTED`, `EXPIRED` or `REVOKED`.
They expire after `TENANT_INVITATION_EXPIRATION_HOURS`, defaulting to 168 hours
(seven days). Expiration is evaluated lazily when an invitation is viewed,
accepted or replaced.

Invitation tokens contain 256 random bits. Only their SHA-256 digest is stored,
which permits indexed lookup without retaining the bearer token. Resending
generates a new token and expiration, invalidating the previous link. A partial
unique index allows only one pending invitation per normalized email and
Tenant.

Acceptance requires an authenticated, verified Laravel user whose normalized
email matches the invitation. Existing users are reused. New users follow the
normal registration, password and verification flow, with the invitation URL
preserved as the intended destination. Acceptance is transactional and
idempotent; the unique Tenant/user membership constraint prevents duplicates.

## Settings permissions

`settings.view` opens the derived Settings Hub and company settings.
`settings.manage` updates company settings. Domain settings continue to use
their existing permissions, including `stores.*`, `payments.settings`,
`inventory.view`, `taxes.*` and `fiscal_settings.*`. The backend remains the
authorization boundary.

The setup summary derives its state from existing domain records. It does not
persist duplicate completion flags and does not block operations.
