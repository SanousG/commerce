---
name: testing-rules
description: Use before writing or running Laravel tests, especially database-backed, destructive, integration, or cross-tenant tests with the local Docker PostgreSQL services.
---

# Testing Rules

- Prefer Feature tests for HTTP flows and framework-integrated behavior.
- Use Unit tests for pure logic when isolation provides value.
- Cover the happy path and important validation, authorization, and failure
  paths.
- Keep tests independent and do not rely on execution order.
- Never use real production data in tests.
- Treat the primary development connection with extreme care because it holds
  local project data and is not the disposable testing database.
- Before running any database-backed test, inspect `phpunit.xml` and the
  effective testing configuration, including connection, host, port, database,
  and relevant environment overrides without exposing credentials.
- Explicitly verify that tests point to `pgsql.testing/commerce_testing`, not
  the local `pgsql` development database or any remote database. `APP_ENV=testing`
  or a different database name alone is not sufficient proof of isolation.
- Never run `migrate:fresh`, `db:wipe`, `RefreshDatabase`, mass truncation, or
  another destructive command or test setup against the development database.
- Use `RefreshDatabase` only after the test guard confirms the local
  `pgsql.testing` host and `commerce_testing` database.
- If a safe, isolated testing database does not exist, create the tests but do
  not execute database-backed tests. Report the exact testing configuration
  still required.
- Do not change credentials or connection settings without explicit user
  authorization.
- Starting with the Tenancy phase, cross-tenant isolation tests are mandatory
  for every tenant-aware feature.
- Add regression coverage for bugs when it meaningfully prevents recurrence.
