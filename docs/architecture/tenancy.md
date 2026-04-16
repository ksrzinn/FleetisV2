# Tenancy

Fleetis v2 is a multi-tenant SaaS where each tenant is a `companies` row.
Three independent layers enforce isolation; an attacker would have to defeat all
three to leak data between companies.

## Layer 1 — Eloquent global scope

`App\Modules\Tenancy\Traits\BelongsToCompany` adds:

- a `CompanyScope` global scope that constrains every query to
  `company_id = auth()->user()->company_id`;
- a `creating` hook that auto-fills `company_id` from the authenticated user.

Models that opt into this trait inherit both behaviours. Bypassing the scope
requires an explicit `->withoutGlobalScopes()`.

## Layer 2 — `EnsureTenantContext` middleware

Aliased as `tenant`, applied to every authenticated route group.

For each request it:
1. Aborts with `403` if the user has no `company_id`.
2. Opens a DB transaction.
3. Runs `SET LOCAL app.current_company_id = :id`.
4. Commits on success, rolls back on any thrown exception.

The middleware is the single point that switches Postgres into "strict" RLS mode
for the request.

## Layer 3 — PostgreSQL Row Level Security

Every table with a `company_id` column has `FORCE ROW LEVEL SECURITY` enabled and
a `<table>_tenant_isolation` policy. The policy is permissive when
`app.current_company_id` is unset (so migrations and seeders can run unrestricted)
and strict when set. Inside an authenticated request — where the middleware always
sets the variable — even raw SQL or a developer mistake that bypasses Eloquent
cannot read or write another tenant's rows.

`RlsCoverageTest` enforces that every tenant-scoped table actually has a policy.

## Signup

`/register` runs `RegisterCompanyAction` inside a single DB transaction:

1. Creates the `Company`.
2. Creates the admin `User` with that `company_id`.
3. Seeds the three default roles (`Admin`, `Operator`, `Financial`) for the
   company via Spatie's teams feature.
4. Calls `setPermissionsTeamId($company->id)` and assigns `Admin` to the user.

Any failure rolls back all four steps — there is no half-created tenant.

The `User` model implements `MustVerifyEmail`, so `/dashboard` (guarded by `verified`)
will bounce a freshly-signed-up user to `/verify-email`. In local dev with
`MAIL_MAILER=log`, the verification link is written to `storage/logs/laravel.log` —
grep for `verify-email/` to find it.

## Test harness

`Tests\TenantTestCase` exposes `actingAsTenant(User $user)` which logs in,
sets the Postgres session variable, and primes the Spatie permission registrar.
Module tests should extend this class when they need to assert tenant-scoped
behaviour.
