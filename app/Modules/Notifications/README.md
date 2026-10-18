# Notifications Module

This module owns in-application admin notifications. It does not own email, SMS,
push delivery, frontend rendering, route registration, or command scheduling.

## Scoping and recipients

- Every notification belongs to a tenant and recipient; `store_id = null` means
  tenant-wide and a store ID means visible only while that store is selected.
- HTTP reads and mutations are scoped to the authenticated user, active tenant,
  and either the current store or tenant-wide records. Ownership failures are 404s.
- `NotificationRecipientResolver::userIds($tenantId, $permissions)` mirrors
  `PermissionChecker`: active members of an active tenant qualify only through an
  active system role carrying any requested permission. OWNER and ADMIN receive
  notifications only when their permission grants qualify.

## Dedupe and lifecycle

- Event keys are stored as `event:<key>` and condition keys as `condition:<key>`.
- PostgreSQL permits only one unresolved, undismissed row per tenant, recipient,
  and key. Concurrent delivery is handled with `INSERT ... ON CONFLICT DO NOTHING`.
- Condition reconciliation updates an existing active row. A severity escalation
  clears `read_at`; unchanged or reduced severity preserves read state.
- Dismissed and resolved rows are historical. A later delivery may create a new
  active row with the same key. Callers needing permanent event idempotency must
  use a durable source event identifier and avoid re-emitting it after closure.
- Retention is intentionally not automated. A future policy may delete old
  resolved/dismissed rows without affecting active dedupe.

## Failure policy

`NotificationManager` validates input and throws on failures so transactional
callers can make an explicit consistency choice. `BestEffortNotificationManager`
is the non-blocking integration API: it reports exceptions through Laravel's
exception reporter and returns `null`. It never silently suppresses a failure.

All action URLs are validated server-side as relative `/admin` paths. Reconciliation
uses active-location total availability and the configured
`commerce.inventory.low_stock_threshold`. The command is registered by Laravel's
command discovery but is not scheduled by this phase.
