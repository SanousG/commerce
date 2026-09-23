---
name: tenancy-rules
description: Use for any tenant, membership, tenant-owned data, jobs, cache, storage, imports, exports, or analytics work requiring strict cross-tenant isolation.
---

# Tenancy Rules

Apply these rules strictly to every tenant-aware feature:

- Never trust or directly adopt a `tenant_id` from request input.
- Resolve the active tenant through `TenantContext` or an equivalent authorized
  mechanism.
- Verify membership and authorization before accessing tenant resources.
- Scope every query for tenant-owned data to the authorized tenant.
- Ensure one tenant can never read, mutate, reference, infer, or delete another
  tenant's resources.
- Carry tenant context explicitly in queued jobs and validate it when the job
  runs.
- Include tenant identity in future cache keys whenever cached data is
  tenant-specific.
- Isolate private storage by tenant and authorize every file operation.
- Ensure imports and exports operate only within the authorized tenant.
- Scope analytics and aggregate reporting so data cannot leak across tenants.
- Avoid unsafe static globals or process-persistent tenant state, especially in
  workers and long-running processes.
- Add cross-tenant tests for every tenant-aware feature, covering denied reads
  and writes where applicable.
- Model users as members of one or more tenants; do not make a fixed
  `users.tenant_id` the primary design.
- Keep `Store` and `Tenant` as separate entities.

Do not implement tenancy merely because this skill was loaded. If the active
phase explicitly does not require tenancy implementation, preserve these rules
for later and make no tenancy code changes.
