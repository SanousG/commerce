# Project Agent Rules

## Project Context

This project is a multi-tenant e-commerce SaaS built with Laravel 13, PHP 8.5,
Vue 3, Inertia.js, TypeScript, Tailwind CSS, PostgreSQL managed by Supabase, and
local Redis through Docker / Laravel Sail.

Development is strictly phase-based. Each phase must be implemented, tested,
reviewed, and explicitly closed before work starts on the next phase. Never
advance to another phase without an explicit user request.

## Architecture

- Use a Laravel Modular Monolith with a single deployment initially.
- Keep the backend, admin frontend, and storefront frontend in the same Laravel
  application. Vue and Inertia live inside this project.
- Do not introduce microservices unless a future explicit decision changes the
  architecture.
- Keep business domains separated through modules as they begin to grow.
- Create modules only when their phase requires them; do not scaffold future
  domains prematurely.
- Prefer the smallest correct design. Avoid ceremonial architecture and
  overengineering.
- Consult the local `ecommerce-architecture` skill for architecture or module
  decisions.

## Multi-Tenancy

- Multi-tenancy is a cross-cutting requirement for every phase that handles
  business data.
- Never trust a `tenant_id` supplied by the frontend or request payload.
- Resolve the tenant through an authorized context and verify the user's
  membership and authorization.
- Correctly isolate every tenant-owned entity and scope every tenant-owned
  query.
- Add cross-tenant isolation tests for all tenant-sensitive functionality.
- A user may belong to multiple tenants through memberships. Do not use a fixed
  `tenant_id` directly on `users` as the primary tenancy design.
- `Store` and `Tenant` are distinct entities and concepts.
- Consult the local `tenancy-rules` skill whenever work is tenant-aware.

## Backend

- Prefer idiomatic Laravel patterns and framework features.
- Keep controllers thin.
- Use Form Requests for validation when appropriate.
- Move business logic out of controllers as it grows.
- Use database transactions for multi-write operations that require atomicity.
- Integrate external providers through adapters or connectors.
- Never call external providers directly from controllers.

## Frontend

- Use Vue 3, Inertia.js, TypeScript, and Tailwind CSS.
- Keep admin and storefront visually and structurally separated even though
  they live in the same application.
- Do not add Nuxt.
- Do not add React.

## Authentication

- Use Laravel session and cookie authentication for the web application.
- Do not use JWT or refresh tokens for the web frontend.
- Do not use Supabase Auth.
- Laravel's `public.users` table is the primary application identity source.
- Consult the local `auth-security-rules` skill for authentication or sensitive
  security work.

## Database

- Production and development PostgreSQL are managed in Supabase; do not assume
  a local PostgreSQL server.
- Do not modify Supabase internal `auth`, `storage`, or `realtime` schemas.
- Keep application tables in `public` unless a future explicit decision says
  otherwise.
- Laravel migrations are the source of truth for application schema changes.
- Never run `migrate:fresh`, `db:wipe`, mass truncation, or another destructive
  database command against Supabase without explicit authorization.
- Never delete remote data on your own initiative.

## Redis

- Use local Redis in development.
- Introduce Redis-backed cache or queues only when a phase needs them.
- Do not introduce Horizon until a phase explicitly requires it.

## Security

- The backend always validates authorization; frontend checks are never a
  security boundary.
- Never expose secrets or print the complete `.env` file.
- Sensitive actions must be designed so they can be audited.
- AI integrations must never receive arbitrary SQL access.

## Testing

- Every functional phase requires tests before it can be closed.
- Multi-tenancy functionality requires cross-tenant isolation tests.
- Add a regression test for a fixed bug when it provides meaningful coverage.
- Before running any database-backed test, inspect `phpunit.xml` and the
  effective testing database configuration.
- Verify that database-backed tests do not use the Supabase database used by
  development or any other shared remote database.
- Never run `migrate:fresh`, `db:wipe`, `RefreshDatabase`, mass truncation, or
  another destructive operation against remote Supabase.
- Use `RefreshDatabase` only after the test guard has confirmed the isolated
  local `pgsql.testing` connection and `commerce_testing` database.
- If no safe isolated testing database exists, write the tests but do not run
  them; report the testing configuration that is still required.
- Do not change remote database credentials without explicit authorization.
- Never use real production data in tests.
- Consult the local `testing-rules` skill before testing database-backed or
  tenant-aware functionality.

## Git Safety

- Do not commit.
- Do not push.
- Do not change branches.
- Do not delete or revert existing work without explicit authorization.

## External Skills

External skills from `skills.sh` may be evaluated later, but must not be
installed automatically. First validate the local rules in this repository.

Initial candidates from `leeovery/claude-laravel`:

- `laravel-architecture`
- `laravel-multi-tenancy`
- `laravel-testing`
- `laravel-quality`

Future candidates from `jpcaparas/superpowers-laravel`:

- `laravel:transactions-and-consistency`
- `laravel:policies-and-authorization`
- `laravel:migrations-and-factories`
- `laravel:queues-and-horizon`
- `laravel:http-client-resilience`
- `laravel:rate-limiting`

## Required Final Response

At the end of every task, report all of the following, even when the answer is
`None` or `Not run`:

A. Files created
B. Files modified
C. Dependencies added or removed
D. Migrations
E. Tests
F. Test results
G. Technical decisions
H. Problems encountered
I. Pending work
J. What was deliberately not implemented
