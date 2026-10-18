# Notifications Module

This module owns tenant-aware administrative notifications, personal delivery preferences, and optional email delivery. It does not own business transitions, the audit ledger, SMS, push, provider credentials, frontend authorization, or route registration.

## Existing center and HTTP security

- The Inertia center provides a paginated list (25), unread/active/resolved, severity and type filters, unread count, mark-one/all-read, dismissal, dates, status, and resource links.
- Every read and mutation starts from the active `TenantContext`, authenticated recipient, and current `StoreContext` (plus tenant-wide rows). Foreign IDs and other recipients return 404.
- Results and unread counts are filtered again by the recipient's current permission for the notification type. A revoked permission therefore hides old content and actions. Linked controllers still authorize and tenant/store-scope the resource again.
- Titles and descriptions are deliberately generic. Financial amounts, customer details, external references, tokens, XML/CDR and credentials do not belong in notification content.

## Recipients and preferences

`NotificationRecipientResolver` selects active members of an active tenant through current active system-role grants. Callers supply the permission matching the linked read surface; notifications are never broadcast indiscriminately.

Preferences are per `(tenant, user)`:

- `email_enabled` defaults to false.
- `muted_types` suppresses both new in-app rows and email for that type.
- `FISCAL_REJECTED` and `FISCAL_UNKNOWN` are mandatory administrative alerts and cannot be muted.
- Preferences do not alter or disable audit recording.

The preference endpoints never accept a tenant ID. Queue delivery re-checks membership, permission, preferences, and email address instead of trusting creation-time values.

## Event matrix

| Type | Source of truth / identity | Scope | Recipient permission | Status |
|---|---|---|---|---|
| `ORDER_PLACED` | committed Order UUID | Store | `orders.view` | Supported event |
| `PAYMENT_CAPTURED` | successful capture transaction UUID | Store | `payments.view` | Supported event |
| `REFUND_PENDING` | pending Refund UUID | Store | `refunds.manage` | Supported condition |
| `REFUND_COMPLETED` | successful refund transaction UUID | Store | `refunds.view` | Supported event |
| `RETURN_REQUESTED` | Return UUID | Store | `returns.manage` | Supported event requiring review |
| `RETURN_APPROVED` | Return UUID / approved transition | Store | `returns.manage` | Supported event |
| `RETURN_AWAITING_INSPECTION` | received Return UUID | Store | `returns.inspect` or `returns.manage` | Supported condition |
| `FULFILLMENT_COMPLETED` | Fulfillment UUID | Store | `fulfillments.view` | Supported event |
| `SHIPMENT_READY` | Shipment UUID | Store | `shipments.view` | Supported event; linked detail also requires `fulfillments.view` |
| `LOW_STOCK`, `OUT_OF_STOCK` | aggregate active-location availability by Variant UUID | Tenant | `inventory.view` | Reconciled condition |
| `PURCHASE_ORDER_TO_RECEIVE` | overdue ordered/partially received PO UUID | Tenant | `procurement.receive` | Reconciled condition |
| `PURCHASE_RECEIPT_POSTED` | posted receipt UUID | Tenant | `procurement.view` | Supported event |
| `PAYMENT_PENDING` | pending Payment UUID | Store | `payments.manage` | Reconciled condition |
| `FISCAL_REJECTED`, `FISCAL_UNKNOWN` | latest FiscalDocument submission state | Store | fiscal view/manage/issue as appropriate | Mandatory reconciled condition |

### Deliberately omitted

- Payment failure: no reliable provider failure event exists. Authorization is not treated as capture.
- Return rejection/completion: existing transitions are reliable but do not require a new administrative action; audit/history remains authoritative.
- Purchase order fully received: receipt-posted is the reliable event; the receiving projection may represent partial or final receipt.
- Shipment shipped/delivered and order completed: informative but not presently actionable; omitted to control volume.
- Team invitation in-app: invitees are not tenant members and cannot satisfy the notification recipient foreign key. The existing invitation mail remains separate.
- Courier events, storefront/customer notifications, SUNAT production delivery, WhatsApp, SMS and browser push.

## Transactions, deduplication, and reconciliation

- Business services call `OperationalNotificationPublisher` inside the transaction that owns the transition. In-app rows roll back with the business mutation.
- Event keys are `event:<stable operation/transition identity>`. Events have permanent idempotency: retrying after read, dismissal, or resolution reuses the historical row. Distinct real transitions must use distinct durable identities (for example payment transaction UUIDs).
- Condition keys are `condition:<resource identity>`. PostgreSQL permits one active row per tenant, recipient and key. Conditions update while active, resolve when recovered, and may create a fresh row after a real recovery/new-failure cycle.
- Severity escalation clears `read_at`; unchanged or reduced severity preserves it.
- Low stock uses total `on_hand - reserved` across active tenant inventory locations and `commerce.inventory.low_stock_threshold`. It is generated only by explicit reconciliation, never by product reads. Recovery resolves the row; a later fall creates a new active row.
- `notifications:reconcile {--tenant=}` is scheduled hourly through Laravel Scheduler with `withoutOverlapping`. It remains idempotent and isolates failures per active Tenant.

## Email and queues

- Email is opt-in and uses Laravel Mail plus the configured queue; no provider dependency or credential storage is introduced.
- `SendAdminNotificationEmail` is queued with `afterCommit`, three attempts, and 60/300 second backoff. Provider failure cannot roll back the already committed business operation.
- A database `emailed_at` claim suppresses concurrent workers and ordinary worker retries. The claim is released if the transport throws.
- Guarantee: ordinary concurrent workers and retries are suppressed by the durable pre-send claim, and observed transport exceptions are retried. It is **not exactly once**: a process crash after claiming but before transport can lose an email, while an ambiguous provider exception after acceptance can cause a retry and duplicate because Laravel Mail exposes no portable external idempotency key.
- Tests use Mail/Queue fakes and `MAIL_MAILER=array`; no automated test contacts an external mail provider.

## Audit boundary and limitations

Audit remains an independent append-only record. A notification failure does not create a replacement audit event, and muting a notification never mutes audit. Notification metadata is operational and must stay bounded and non-sensitive.

Known limitations: no retention policy exists; there is no delivery-attempt ledger; queue infrastructure is the existing database driver and requires an operator-managed worker; both `php artisan schedule:work` (or an external cron invoking `schedule:run`) and `php artisan queue:work` are operator-managed deployment concerns; production provider health/credentials are also deployment concerns.
