# Audit module

Phase 24 provides an append-only, tenant-scoped activity ledger. Route and UI integration are intentionally external to this module.

## Write API

Resolve `AuditRecorder` from the container and call `record(Tenant $tenant, AuditActorType $actorType, AuditAction $action, AuditSubjectType $subjectType, string $subjectId, array $metadata = [], ?Store $store = null, ?User $actor = null, ?string $correlationId = null, ?string $idempotencyKey = null, ?CarbonInterface $occurredAt = null): AuditEntry`.

The recorder writes directly and does not open or commit a transaction. Call it inside the transaction that owns the audited mutation when atomicity is required. Database exceptions are not caught. The optional idempotency key is unique per tenant when present.

Only enum action keys and subject types are accepted. Add future vocabulary to `AuditAction` and `AuditSubjectType`; do not place class names in the ledger. `USER` requires an explicitly supplied active tenant member. `SYSTEM` requires a null actor. Request IP and user-agent are contextual evidence only and never establish actor identity.

Metadata must be a bounded JSON-compatible array. Eloquent models and unsupported objects are rejected. Sensitive keys are recursively replaced with `[REDACTED]`, including password, token, secret/private-key, certificate/SUNAT password, authorization/auth-header, credential/API-key, XML, and CDR material. Store identifiers and safe before/after scalar snapshots instead of models or complete payloads.

## Read API

Wire a tenant-resolved authenticated GET route externally to `ActivityController@index`. The request checks the dynamic `audit.view` key through `PermissionChecker`; the migration grants it only to system OWNER and ADMIN roles. Filters are `from`, `to`, `actor` (`all`, `system`, or a tenant user ID), `action`, `subject_type`, `subject_id`, and `store` (`current`, `all`, or a store UUID belonging to the tenant). Results are JSON, 50 per page, ordered by `occurred_at` then `id` descending.

Every query starts with the resolved tenant. Store and actor selectors are checked against that tenant. Output contains normalized actor/subject/store objects, a human summary, sanitized metadata, request context, occurrence time, and correlation ID. It never serializes related models wholesale.

## Retention and immutability

Audit rows have no `updated_at` and must never be edited or deleted. PostgreSQL rejects UPDATE and DELETE through a trigger, including direct SQL and Eloquent operations. Tenant deletion is restricted so the ledger must be retained or deliberately handled first; store and user deletion null their references while preserving the event and actor type. The module does not implement retention, export, UI, routes, automatic model observers, or retroactive instrumentation of existing modules.
