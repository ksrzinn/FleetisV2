# Postgres setup

Fleetis v2 runs migrations and application queries under the same non-superuser role.
Tenancy is enforced via PostgreSQL Row Level Security using `FORCE` so even the table
owner is subject to the policy on DML.

## One-time role + database creation

```sql
CREATE USER fleetis WITH PASSWORD 'fleetis' CREATEDB;
CREATE DATABASE fleetis_v2 OWNER fleetis;
```

## How RLS is applied

- Every tenant-scoped table gets `ENABLE ROW LEVEL SECURITY` + `FORCE ROW LEVEL SECURITY`.
- A policy `<table>_tenant_isolation` is **permissive when `app.current_company_id` is unset**
  (migrations, seeders, test bootstrap) and **strict when set**
  (authenticated request after `EnsureTenantContext` runs).
- The `EnsureTenantContext` middleware opens a transaction and runs
  `SET LOCAL app.current_company_id = :id` for every authenticated request.

The permissive-when-unset pattern lets framework bootstrapping run without every
seeder/migration needing to know about RLS, while still catching raw-SQL cross-tenant
leakage inside an authenticated request — the middleware is the single enforcement point.

## Why a second migration path (`database/migrations/rls/`)?

Some tables (e.g. the test-only `tenant_probes` table) are created after the main
migrations run. Moving RLS enablement into `database/migrations/rls/` lets us re-run
RLS application after every epic's new tables land without editing the original migration.
`AppServiceProvider::boot()` registers both `database/migrations/tests/` (only in the
`testing` environment) and `database/migrations/rls/` so tests always have RLS enforced
on test-only tables too.

The `RlsCoverageTest` is the load-bearing guardrail: it inspects
`information_schema.columns` for every table with a `company_id` column and asserts each
one has `FORCE ROW LEVEL SECURITY` enabled and a tenant-isolation policy in
`pg_policies`. Adding a new tenant-scoped table without wiring it into
`database/migrations/rls/` will fail this test in CI.
