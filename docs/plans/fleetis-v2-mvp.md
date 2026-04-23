# Fleetis v2 — Freight/Fleet SaaS Implementation Plan

> **For agentic workers:** This is a phased architectural roadmap for an MVP spanning ~12–14 weeks (single engineer). Each epic will be expanded into its own detailed step-level TDD plan when it is picked up. Do not attempt to execute this document end-to-end as a single task sequence.

**Goal:** Ship an MVP SaaS for Brazilian freight/fleet operators — fleet management, freight lifecycle, and financial module (expenses, AR, AP, dashboards).

**Architecture:** Laravel 11 modular monolith + Inertia.js + Vue 3 (Options API) + PostgreSQL. Multi-tenant shared DB with `company_id` scoping plus PostgreSQL Row-Level Security. Roles: Admin / Operator / Financial (strict separation). No Brazilian fiscal docs (CT-e/NF-e) in MVP. No driver app in MVP.

**Tech Stack:** Laravel 11, Breeze (Inertia+Vue3 variant), PostgreSQL 16, Inertia.js, Vue 3 Options API, TailwindCSS, ApexCharts (via `vue3-apexcharts`).

---

## Context

Greenfield build for a Brazilian freight/fleet operator SaaS. Working directory `/var/www/html/projects/fleetis-v2` is empty. The user supplied a detailed domain brief covering vehicle categories (VUC, toco, 3/4, truck, semi-trailer, rodotrem, bitrem), two freight pricing models (fixed per-client rate tables vs. per-client-per-state per-km rates), a freight lifecycle state machine, and a financial module (expenses, fuel, maintenance, AP with installments, AR, dashboards). No codebase exists yet, so this plan is architectural and phased rather than line-level.

The plan locks in decisions made during brainstorming so that each epic starts with clear constraints.

---

## Confirmed Decisions

| # | Decision | Choice |
|---|---|---|
| D1 | Tenancy | Multi-tenant, shared DB, `company_id` scoping |
| D2 | Auth/scaffold | Laravel Breeze (Inertia + Vue 3 starter) |
| D3 | Roles | Admin, Operator, Financial — strict separation |
| D4 | Driver app | Not in MVP |
| D5 | Fiscal docs (CT-e/NF-e) | Not in MVP |
| D6 | Driver compensation modes | Percentage, Fixed/freight, Monthly salary — all three |
| D7 | Expenses linkage | Flexible: standalone / vehicle / freight |
| D8 | AR tracking | Yes — receivable auto-created on transition to Awaiting Payment |
| D9 | Freight value timing | Locked at transition to Awaiting Payment; no estimated value during In Route |
| D10 | Multi-tenancy package | **Homebrew** `BelongsToCompany` trait + global scope + PostgreSQL RLS. No `stancl/tenancy`, no `spatie/laravel-multitenancy` |
| D11 | Vehicle/trailer modeling | Single `vehicles` table with `kind` enum; trailer FK lives on `freights.trailer_id`, not on vehicles |
| D12 | Expenses schema | Explicit nullable FKs (`vehicle_id`, `freight_id`) + CHECK constraint. **Not** polymorphic |
| D13 | Driver compensation schema | STI (`driver_compensations` with `type` discriminator) + CHECK constraint + `effective_from`/`effective_to` history |
| D14 | Freight state machine | `spatie/laravel-model-states` — `AwaitingKm` conditional on per-km pricing |
| D15 | Bills (AP) storage | One `bills` + `bill_installments` schema; recurring generates rows via scheduler, installment generates all N up front |
| D16 | Charting library | ApexCharts via `vue3-apexcharts` |

---

## Architecture Overview

### Module structure

```
app/Modules/
  Tenancy/         — companies, tenant middleware, BelongsToCompany trait, RLS wiring
  Identity/        — auth, roles, permissions (Spatie teams = companies)
  Fleet/           — vehicles, drivers, driver_compensations
  Commercial/      — clients, freight rate tables
  Operations/      — freights, lifecycle state machine, status history
  Finance/         — expenses, fuel, maintenance, bills, receivables, payments
  Reporting/       — dashboards, aggregations, reports
```

Each module owns its `Models/`, `Http/Controllers/`, `Http/Requests/`, `Actions/`, `Policies/`, `Services/`, `Events/`, `Listeners/`. Cross-module communication is via **domain events** and service classes. Controllers stay thin; business logic lives in invokable **Action classes**.

### Multi-tenancy (D10)

Three defensive layers:
1. `BelongsToCompany` trait → global scope + `creating` hook that auto-fills `company_id`.
2. `EnsureTenantContext` middleware asserts `auth()->user()->company_id` is present on every authenticated request.
3. PostgreSQL Row-Level Security policies on every tenant-scoped table, driven by a session variable set per request (`SET LOCAL app.current_company_id = ?`). Last-line defense against scope bypass.

### Roles (D3)

`spatie/laravel-permission` v6 with `teams` feature (team_id = company_id). Policies:
- **Admin** — everything.
- **Operator** — Fleet, Commercial, Operations (freight CRUD + status transitions). No Finance writes.
- **Financial** — Finance (expenses, bills, receivables, payments) + Reporting dashboards. No Operations writes.
- No overlap. Revisit if scaling pain emerges.

---

## Database Schema (PostgreSQL)

All tenant tables: `id bigserial pk`, `company_id bigint not null FK`, composite index `(company_id, <lookup>)`, `created_at`, `updated_at`. Soft deletes per D-risk #5 (entity tables only).

### Tenancy / Identity
- `companies` — `name`, `cnpj`, `timezone`, `status`.
- `users` — `company_id`, `name`, `email` (unique per company), `password`, `email_verified_at`.
- Spatie tables: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` (all with `team_id`=`company_id`).

### Fleet
- `vehicle_types` — `code`, `label`, `requires_trailer bool`. Data-driven.
- `vehicles` — `kind` enum(`vehicle`,`trailer`), `vehicle_type_id` FK, `license_plate` (unique per company), `renavam`, `brand`, `model`, `year`, `notes`, `active`.
- `drivers` — `name`, `phone`, `birth_date`, `cpf` (unique per company), `active`.
- `driver_compensations` — `driver_id`, `type` enum, `percentage` num(5,2) null, `fixed_amount` num(12,2) null, `monthly_salary` num(12,2) null, `effective_from`, `effective_to` null. CHECK: exactly one value column non-null matching `type`.

### Commercial
- `clients` — `name`, `document` (CPF or CNPJ), `email`, `phone`, `address_*`, `active`.
- `client_freight_tables` — `client_id`, `name`, `pricing_model` enum(`fixed`,`per_km`), `active`.
- `fixed_freight_rates` — `client_freight_table_id`, `name` ("Sorocaba 3"), `price`, `avg_km` null, `tolls` null, `fuel_cost` null. Unique `(table_id, name)`.
- `per_km_freight_rates` — `client_id`, `state` char(2), `rate_per_km` num(10,4). Unique `(company_id, client_id, state)`.

### Operations
- `freights` — `client_id`, `vehicle_id`, `trailer_id` null, `driver_id`, `pricing_model`, `fixed_rate_id` null, `per_km_rate_id` null, `origin` null, `destination` null, `distance_km` null, `toll` null, `fuel_price_per_liter` null, `freight_value` null (locked at AwaitingPayment), `status` (default `to_start`), `started_at`, `finished_at`, `completed_at`, soft deletes. Indexes: `(company_id, status)`, `(company_id, client_id, completed_at)`, `(company_id, vehicle_id, started_at)`.
- `freight_status_history` — append-only audit: `freight_id`, `from_status`, `to_status`, `user_id`, `occurred_at`, `notes`.

### Finance
- `expenses` — `category`, `amount`, `incurred_on`, `description`, `vehicle_id` null, `freight_id` null. CHECK: at most one of vehicle/freight non-null.
- `fuel_records` — `vehicle_id`, `driver_id` null, `freight_id` null, `liters`, `price_per_liter`, `total_cost`, `odometer_km`, `fueled_at`, `station` null.
- `maintenance_records` — `vehicle_id`, `type`, `description`, `cost`, `odometer_km`, `performed_on`, `provider` null.
- `bills` — `supplier`, `description`, `bill_type` enum(`one_time`,`recurring`,`installment`), `total_amount`, `due_date`, `recurrence_cadence` null, `recurrence_day` null, `recurrence_end` null, `installment_count` null.
- `bill_installments` — `bill_id`, `sequence`, `amount`, `due_date`, `paid_at` null, `paid_amount` null.
- `receivables` — `client_id`, `freight_id` null, `amount_due`, `amount_paid` default 0, `due_date`, `status` enum(`open`,`partially_paid`,`paid`,`overdue`).
- `payments` — bounded polymorphic: `payable_type` enum(`receivable`,`bill_installment`), `payable_id`, `amount`, `paid_at`, `method`, `notes`.

### Key enforcement rules
- **Freight requires trailer when vehicle_type.requires_trailer**: enforce in three layers — Form Request validation + Action class re-check + PostgreSQL trigger. Not a model observer.
- **Per-km freight is single-state**: enforced by the `per_km_rate_id` FK (unique per `(client_id, state)`) and the freight's `state` column matching the rate's state.
- **Soft delete strategy**: soft-delete entity tables (clients, drivers, vehicles, freights, bills). Hard-delete history/transactional tables (status_history, payments, installments, fuel, maintenance, receivables).

---

## MVP Epic Phasing

Dependency-ordered. Each epic will get its own step-level TDD sub-plan when started. Sizes: S ≤ 3 days, M ≤ 1.5 weeks, L ≤ 3 weeks (one focused engineer).

### Epic 1 — Foundation (L)
**Depends on:** none. **Deliverables:**
- Laravel 11 install, Breeze Inertia+Vue3 scaffold, Tailwind, ApexCharts wiring, base layout.
- `companies` table + signup flow (company + admin user together).
- `BelongsToCompany` trait, global scope, model events.
- PostgreSQL RLS policies + session variable middleware.
- Spatie permission v6 with teams, Admin/Operator/Financial roles seeded.
- Policy base classes + `EnsureTenantContext` middleware.
- Cross-tenant leakage test module (every future index/show gets a paired test).
- CI: PHPUnit, Pint (style), Larastan (static analysis), ESLint.

### Epic 2 — Fleet: Vehicles & Drivers (M)
**Depends on:** 1. **Deliverables:**
- `vehicle_types` seeded (VUC, toco, 3/4, truck, semi_trailer, rodotrem, bitrem; `requires_trailer` flag set).
- Vehicles CRUD (plate, kind, type, renavam, brand, model, year, active filter).
- Drivers CRUD (name, phone, birth_date, CPF with validation).
- `driver_compensations` CRUD with STI classes (Percentage, FixedPerFreight, MonthlySalary) + CHECK + time-ranging.
- CPF/CNPJ validation rule (vet `geekcom/validator-docs` for active maintenance; else `brazanation/documents`).

### Epic 3 — Commercial: Clients & Freight Tables (M)
**Depends on:** 1. **Deliverables:**
- Clients CRUD with CPF/CNPJ validation.
- Fixed rate tables per client: table CRUD + fixed_rates CRUD ("Sorocaba 3"-style routes with optional avg_km, tolls, fuel_cost).
- Per-km rates per client per BR state: CRUD with unique constraint on (client, state).
- Import UX consideration (CSV upload deferred to post-MVP).

### Epic 4 — Operations: Freights & Lifecycle (L) ✅ DONE
**Depends on:** 2, 3. **Deliverables:**
- `spatie/laravel-model-states` freight states: `ToStart`, `InRoute`, `Finished`, `AwaitingPayment`, `Completed`.
  - Note: `AwaitingKm` was dropped; distance_km is captured during InRoute→Finished transition for per_km freights.
- `consumo_medio` (km/L) field added to vehicles for fuel cost estimation.
- Freight creation 4-step wizard (client → pricing model / rate → vehicle + trailer → review).
- "Requires trailer" rule enforced at 3 layers (Request / Action / DB trigger).
- Status transitions with guarded validation:
  - InRoute → Finished captures `distance_km` (required for per_km), `toll`, `fuel_price_per_liter`.
  - Finished → AwaitingPayment **locks `freight_value`** (persisted) + dispatches `FreightEnteredAwaitingPayment` event.
  - `freight_value` computed: fixed rate lookup from `FixedFreightRatePrice`; per_km via `bcmul(distance_km, rate_per_km, 2)`.
  - AwaitingPayment → Completed (manual, receivable payment gating deferred to Epic 6).
- `freight_status_history` auto-populated on every transition via FreightObserver.
- `spatie/laravel-activitylog` for user-level audit.

### Epic 5 — Finance: Expenses, Fuel, Maintenance (M)
**Depends on:** 2, 4. **Deliverables:**
- Expenses CRUD (category, amount, optional vehicle or freight link, CHECK enforcement).
- Fuel records CRUD (vehicle required, optional driver/freight, odometer, liters, cost).
- Maintenance records CRUD (vehicle required, type, cost, odometer, date, provider).
- Index filters by vehicle, date range, freight.

### Epic 6 — Finance: Accounts Receivable (S)
**Depends on:** 4. **Deliverables:**
- Receivable listener on `FreightEnteredAwaitingPayment`.
- Receivables index with filters (client, status, due date).
- Payment recording screen → `payments` + updates receivable `amount_paid`/`status`.
- Receivable full-paid triggers `AwaitingPayment → Completed` on the linked freight.
- Overdue detection (daily job flips `status=open` to `overdue` past due_date).

### Epic 7 — Finance: Accounts Payable / Bills (M)
**Depends on:** 1. Can run in parallel with Epic 4. **Deliverables:**
- Bills CRUD (one-time / recurring / installment).
- Installment bills: generate all N installments on creation.
- Recurring bills: daily scheduler generates upcoming installments (e.g. next cadence period) and stops at `recurrence_end`.
- Installment payment recording (partial + full) via `payments`.
- Bill detail view: total value + outstanding balance + paid count / total count.

### Epic 8 — Reporting: Dashboards & Summaries (L)
**Depends on:** 4, 5, 6, 7. **Deliverables:**
- Financial dashboard (ApexCharts): revenue vs. expenses, AR outstanding, AP outstanding, freight volume, recent activity.
- Per-vehicle report: revenue contribution, fuel cost, maintenance cost, utilization.
- Per-freight drill-down.
- Daily / weekly / monthly aggregation endpoints (scoped to company_id via RLS).
- Read-model service class layer (so queries can be pointed at materialized views later without controller changes).

---

## Recommended Libraries

### Composer
- `laravel/breeze` ^2 — Inertia + Vue3 scaffold
- `spatie/laravel-permission` ^6 — roles w/ teams
- `spatie/laravel-model-states` ^2 — freight state machine
- `spatie/laravel-activitylog` ^4 — audit trail
- `spatie/laravel-data` ^4 — typed DTOs for Inertia props and Action inputs
- `spatie/laravel-query-builder` ^6 — filtering/sorting on indexes
- `tighten/parental` ^1 — STI for driver compensations
- CPF/CNPJ validator: evaluate `geekcom/validator-docs` vs `brazanation/documents` during Epic 2 — pick based on current maintenance status.

### NPM
- `@inertiajs/vue3`
- `vue3-apexcharts` + `apexcharts`
- `tailwindcss` ^3 (Breeze default)
- `lodash-es` (debounced search)

---

## Critical Files / Paths (to be created)

Since this is greenfield, every file is new. Canonical paths the plan assumes:

- `app/Modules/Tenancy/Traits/BelongsToCompany.php`
- `app/Modules/Tenancy/Http/Middleware/EnsureTenantContext.php`
- `app/Modules/Fleet/Models/{Vehicle,Driver,DriverCompensation}.php`
- `app/Modules/Commercial/Models/{Client,ClientFreightTable,FixedFreightRate,PerKmFreightRate}.php`
- `app/Modules/Operations/Models/{Freight,FreightStatusHistory}.php`
- `app/Modules/Operations/States/{ToStart,InRoute,Finished,AwaitingKm,AwaitingPayment,Completed}.php`
- `app/Modules/Operations/Actions/{CreateFreightAction,TransitionFreightAction}.php`
- `app/Modules/Finance/Models/{Expense,FuelRecord,MaintenanceRecord,Bill,BillInstallment,Receivable,Payment}.php`
- `database/migrations/` — one migration per table, ordered by dependency.
- `database/seeders/{VehicleTypeSeeder,RoleSeeder}.php`
- `resources/js/Pages/<Module>/*.vue` — Inertia pages (Options API).

---

## Verification Strategy

Per-epic verification is defined in each sub-plan. Overall MVP verification:

1. **Automated tests:**
   - PHPUnit feature tests per module (minimum happy path + one tenant-leak test per endpoint).
   - Policy tests: verify role boundaries (Operator cannot hit Finance endpoints, Financial cannot create freights).
   - State machine tests: every valid transition + every guarded rejection.
   - `php artisan test` must pass in CI on every PR.
2. **Static analysis:** Larastan level 6+ clean.
3. **Manual E2E acceptance scripts:** one per epic, documenting the golden path (e.g. "create freight → transition through lifecycle → record receivable payment → verify dashboard totals update").
4. **Data leakage check:** seed two companies, log in as each, verify no cross-visibility anywhere.

---

## Open Risks / Deferred

| Risk | Mitigation / When to revisit |
|---|---|
| Report performance at scale | Reporting module uses a service-class seam so dashboards can be pointed at materialized views later without touching controllers. Revisit when a company hits ~50k freights. |
| Audit immutability for finance | `activitylog` is editable. If regulatory pressure arrives, add append-only ledger tables for `payments` + `receivable` status changes. |
| CT-e / NF-e | Deferred. When it arrives, new `Fiscal` module with SEFAZ integration. Do not let its future shape influence current schema. |
| Driver app | Deferred. Sanctum tokens will coexist with Breeze sessions. |
| Timezone handling | Store UTC, present in company.timezone. Verify reports correctly bucket by company-local day. |
| Multi-tenancy data leakage | 3 defensive layers (scope, middleware, RLS) + mandatory per-endpoint tenant-leak test. |

---

## Next Step

After approval, Epic 1 (Foundation) is expanded into its own step-level TDD plan (migrations, trait, middleware, RLS, Breeze scaffold, permission seed) and executed via `superpowers:executing-plans` or `superpowers:subagent-driven-development`.
