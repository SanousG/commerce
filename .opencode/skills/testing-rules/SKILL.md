---
name: testing-rules
description: Use before writing or running Laravel tests, especially database-backed, destructive, integration, or cross-tenant tests against the currently remote Supabase configuration.
---

# Testing Rules

- Prefer Feature tests for HTTP flows and framework-integrated behavior.
- Use Unit tests for pure logic when isolation provides value.
- Cover the happy path and important validation, authorization, and failure
  paths.
- Keep tests independent and do not rely on execution order.
- Never use real production data in tests.
- Treat the primary connection with extreme care because it points to remote
  Supabase infrastructure used for development.
- Before running any database-backed test, inspect `phpunit.xml` and the
  effective testing configuration, including connection, host, port, database,
  and relevant environment overrides without exposing credentials.
- Explicitly verify that tests do not point to the Supabase database used by
  development or another shared remote database. `APP_ENV=testing` or a
  different database name alone is not sufficient proof of isolation.
- Never run `migrate:fresh`, `db:wipe`, `RefreshDatabase`, mass truncation, or
  another destructive command or test setup against remote Supabase.
- Use `RefreshDatabase` only after the test guard confirms the local
  `pgsql.testing` host and `commerce_testing` database.
- If a safe, isolated testing database does not exist, create the tests but do
  not execute database-backed tests. Report the exact testing configuration
  still required.
- Do not change remote credentials or connection settings without explicit
  user authorization.
- Recommend and prepare, in a later authorized task, a separate PostgreSQL
  database used exclusively for testing.
- Starting with the Tenancy phase, cross-tenant isolation tests are mandatory
  for every tenant-aware feature.
- Add regression coverage for bugs when it meaningfully prevents recurrence.
