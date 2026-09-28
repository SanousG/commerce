# Store administration

Stores are operational channels within a Tenant. Their `currency`, `timezone`
and `locale` are explicit commercial settings; browser timezone is never used
as authority. Store updates are always scoped through the active Tenant.

Owners and administrators can list, create, edit, activate and deactivate
Stores according to the existing `stores.*` permissions. Archiving is not
introduced by Phase 21. Deactivating the selected Store clears its session
context so middleware cannot continue operating with a stale Store.

Company identity, team membership and invitations do not belong to a Store.
Changing Stores therefore never changes the Team page.
