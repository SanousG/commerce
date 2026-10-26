# Repository Agent Guide

## Scope and architecture

- This is one Laravel 13 modular monolith: backend, admin Inertia/Vue UI, and future storefront deploy together. Do not introduce microservices, Nuxt, or React.
- Business code lives in `app/Modules/<Domain>/{Application,Domain,Http}`. HTTP routes remain centralized in `routes/web.php`; module providers that need registration are listed in `bootstrap/providers.php`.
- Read the affected `app/Modules/<Domain>/README.md` before changing a domain. These files document snapshot boundaries, ownership, state transitions, and deliberately unsupported behavior that filenames do not reveal. For SUNAT work, also read `app/Modules/Fiscal/Infrastructure/Sunat/README.md`.
- Development is phase-based. Do not scaffold a future domain or advance/close another phase without an explicit request; a functional phase needs tests and review before closure.
- Use the local `ecommerce-architecture` skill for module or boundary decisions.

## Tenant, store, and identity boundaries

- A Tenant is the company; a Store is an operational channel. Membership and company settings are tenant-wide, while currency, timezone, locale, listings, payment settings, and fiscal series are store-owned. Changing Store must not change membership.
- Users can belong to multiple Tenants through `tenant_members`; never add or rely on a fixed `users.tenant_id`.
- Never accept request/frontend `tenant_id` as authority. Resolve an active authorized membership through `TenantContext`/`tenant` middleware, and resolve store-owned work through `StoreContext`/`store` middleware. Scope every tenant-owned query, including route-model lookups.
- Add explicit cross-tenant isolation coverage for every tenant-aware feature. Load the local `tenancy-rules` skill for tenant-owned data, jobs, cache, files, imports/exports, or analytics.
- Web authentication is Laravel session/cookie auth backed by `public.users`; do not add JWT/refresh-token web auth or Supabase Auth. Backend Gates/policies and scoped queries are authoritative; hidden UI is not authorization. Load `auth-security-rules` for account/authentication work.

## Data and external-service safety

- Development uses the local Docker PostgreSQL service `pgsql`; testing uses the separate `pgsql.testing/commerce_testing` service. Supabase is not currently used. Always inspect `compose.yaml` and Laravel's effective configuration rather than assuming hostnames, ports, or database names.
- Never run `migrate:fresh`, `db:wipe`, broad truncation, or destructive data commands against development. Do not alter credentials or delete data without explicit authorization.
- `composer setup` is not a harmless bootstrap: it executes `php artisan migrate --force` against the current environment. Inspect the effective database target before using it.
- Redis and Mailpit are local Compose services. Do not add Redis-backed queues/cache or Horizon merely because the services exist.
- Automated tests must not contact SUNAT. Production SUNAT transport is intentionally unavailable; never enable production or resend an `UNKNOWN`/`SUBMITTED` attempt without explicit, manual reconciliation.
- Never print the full `.env`, credentials, certificates, XML/CDR payloads, or other secrets.

## Commands

- Install locked dependencies: `composer install` and `npm ci`.
- Start the complete local stack: `docker compose up -d`. The services are `laravel.test`, `pgsql`, `pgsql.testing`, `redis`, and `mailpit`.
- Aggregate development command: `composer dev`; Laravel 13 starts the HTTP server, queue listener, Pail when available, and Vite. Ensure its configured database/services are intentional first.
- Frontend checks: `npm run test:i18n`, `npm run typecheck` and `npm run build`. There is no configured JS lint script.
- PHP formatting: `vendor/bin/pint path/to/changed.php`. Avoid repository-wide or `--dirty` formatting when unrelated worktree changes exist.

## Tests

- Before database-backed or tenant-aware tests, load `testing-rules` and inspect `phpunit.xml` plus the effective connection.
- Tests are designed to run inside Compose. `phpunit.xml` forces PostgreSQL at `pgsql.testing`, database/user `commerce_testing`; `tests/TestCase.php` fails closed for any other target. This hostname is Docker-internal, so host-side database tests will not work safely.
- Start the stack, then verify isolation first:
  `docker compose exec laravel.test php artisan test tests/Feature/TestingDatabaseConnectionTest.php`
- Full suite: `docker compose exec laravel.test php artisan test`.
- One file: `docker compose exec laravel.test php artisan test tests/Feature/<Domain>/<TestFile>.php`.
- One test method: `docker compose exec laravel.test php artisan test --filter=test_method_name`.
- `RefreshDatabase` is used widely and is allowed only after the guard confirms the isolated local `pgsql.testing/commerce_testing` database. If that service is unavailable or the guard fails, write tests but do not run database-backed tests.

## Change discipline

- Do not commit, push, switch branches, or discard existing work unless explicitly requested. The worktree may already contain substantial in-progress changes.
- Do not install external skills or dependencies speculatively.
- End every task with: files created; files modified; dependencies added/removed; migrations; tests; test results; technical decisions; problems; pending work; and deliberately omitted work. Report `None` or `Not run` where applicable.
