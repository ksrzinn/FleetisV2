# Epic 2 — Fleet Module Design Spec

**Date:** 2026-04-16
**Status:** Approved
**Epic:** 2 — Fleet: Vehicles & Drivers
**Depends on:** Epic 1 (Foundation — multi-tenancy, auth, roles, RLS)

---

## Goal

Implement the Fleet module: vehicle management (trucks, trailers), driver management, and driver compensation tracking with full history per compensation type.

---

## Decisions Made

| # | Topic | Decision |
|---|---|---|
| F1 | CPF/CNPJ validation | `geekcom/validator-docs ^3.12` — works out-of-the-box with Laravel 11, ships `cpf`/`cnpj`/`cpf_cnpj` rules, Portuguese messages included |
| F2 | STI for compensations | `tighten/parental ^1` — `DriverCompensation` parent + 3 subclasses; `type` column is string (not Postgres enum) to avoid Parental discriminator friction |
| F3 | Business logic location | Invokable Action classes (not Services). Services used only for stateful coordination. |
| F4 | Compensation concurrency | A driver may have multiple active compensations simultaneously, **one per type**. Creating a new compensation of an already-active type auto-closes the previous one (`effective_to = today`). |
| F5 | Compensation "active" definition | `effective_to IS NULL` |
| F6 | Role access to Fleet | Admin + Operator: full CRUD. Financial: read-only (viewAny + view). |
| F7 | Compensation UX | Separate page `/drivers/{driver}/compensations` — active compensations + history table + inline add form. |
| F8 | Vehicle/Driver detail page | No dedicated `show` page — edit page doubles as detail view. |

---

## Database Schema

### `vehicle_types` (seeded, not user-managed)

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| code | varchar unique | vuc, toco, three_quarters, truck, semi_trailer, rodotrem, bitrem |
| label | varchar | Human-readable Portuguese label |
| requires_trailer | boolean | true for semi_trailer, rodotrem, bitrem |
| created_at / updated_at | timestamps | |

Seeded via `VehicleTypeSeeder`. No CRUD routes.

### `vehicles`

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| company_id | bigint FK | tenant scope via BelongsToCompany |
| kind | enum(vehicle, trailer) | |
| vehicle_type_id | bigint FK | → vehicle_types |
| license_plate | varchar | unique per company: UNIQUE(company_id, license_plate) |
| renavam | varchar null | |
| brand | varchar | |
| model | varchar | |
| year | smallint | |
| notes | text null | |
| active | boolean default true | |
| deleted_at | timestamp null | soft delete |
| created_at / updated_at | timestamps | |

Indexes: `(company_id, active)`, `(company_id, license_plate)`.

### `drivers`

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| company_id | bigint FK | tenant scope |
| name | varchar | |
| phone | varchar null | |
| birth_date | date null | |
| cpf | varchar(14) | formatted 000.000.000-00, unique per company: UNIQUE(company_id, cpf) |
| active | boolean default true | |
| deleted_at | timestamp null | soft delete |
| created_at / updated_at | timestamps | |

Index: `(company_id, active)`.

### `driver_compensations`

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| company_id | bigint FK | tenant scope |
| driver_id | bigint FK | → drivers |
| type | varchar | percentage, fixed_per_freight, monthly_salary |
| percentage | decimal(5,2) null | non-null only when type=percentage |
| fixed_amount | decimal(12,2) null | non-null only when type=fixed_per_freight |
| monthly_salary | decimal(12,2) null | non-null only when type=monthly_salary |
| effective_from | date | |
| effective_to | date null | null = currently active |
| created_at / updated_at | timestamps | |

**Constraints:**
- DB CHECK: exactly one value column non-null, matching `type`
- Partial unique index: `UNIQUE(driver_id, type) WHERE effective_to IS NULL` — enforces one active per type per driver
- No soft deletes — history rows are the audit trail

---

## Module Structure

```
app/Modules/Fleet/
├── Models/
│   ├── VehicleType.php
│   ├── Vehicle.php
│   ├── Driver.php
│   ├── DriverCompensation.php          # parent (HasChildren)
│   ├── Compensations/
│   │   ├── PercentageCompensation.php
│   │   ├── FixedPerFreightCompensation.php
│   │   └── MonthlySalaryCompensation.php
├── Actions/
│   ├── CreateVehicleAction.php
│   ├── UpdateVehicleAction.php
│   ├── CreateDriverAction.php
│   ├── UpdateDriverAction.php
│   └── UpsertDriverCompensationAction.php
├── Http/
│   ├── Controllers/
│   │   ├── VehicleController.php
│   │   ├── DriverController.php
│   │   └── DriverCompensationController.php
│   └── Requests/
│       ├── VehicleRequest.php
│       ├── DriverRequest.php
│       └── DriverCompensationRequest.php
└── Policies/
    ├── VehiclePolicy.php
    ├── DriverPolicy.php
    └── DriverCompensationPolicy.php

database/
├── migrations/
│   ├── 2026_04_16_*_create_vehicle_types_table.php
│   ├── 2026_04_16_*_create_vehicles_table.php
│   ├── 2026_04_16_*_create_drivers_table.php
│   └── 2026_04_16_*_create_driver_compensations_table.php
└── seeders/
    └── VehicleTypeSeeder.php

resources/js/Pages/Fleet/
├── Vehicles/
│   ├── Index.vue
│   └── Form.vue              # shared create/edit
└── Drivers/
    ├── Index.vue
    ├── Form.vue               # shared create/edit
    └── Compensations/
        └── Index.vue          # active + history + inline add form
```

---

## Business Logic

### UpsertDriverCompensationAction

Runs in a DB transaction:
1. Validate no conflicting active record (belt-and-suspenders before DB constraint fires)
2. Set `effective_to = today` on any existing active compensation of the same type for this driver
3. Insert new `driver_compensations` row with `effective_to = null`

### VehicleType seeding

`VehicleTypeSeeder` runs in `DatabaseSeeder::run()`. Idempotent via `updateOrCreate(['code' => ...])`.

| code | label | requires_trailer |
|---|---|---|
| vuc | VUC | false |
| toco | Toco | false |
| three_quarters | 3/4 | false |
| truck | Truck | false |
| semi_trailer | Semirreboque | true |
| rodotrem | Rodotrem | true |
| bitrem | Bitrem | true |

---

## Routes

All routes under `auth`, `verified`, `tenant` middleware group.

```
GET    /vehicles                              → VehicleController@index
GET    /vehicles/create                       → VehicleController@create
POST   /vehicles                              → VehicleController@store
GET    /vehicles/{vehicle}/edit               → VehicleController@edit
PUT    /vehicles/{vehicle}                    → VehicleController@update
DELETE /vehicles/{vehicle}                    → VehicleController@destroy

GET    /drivers                               → DriverController@index
GET    /drivers/create                        → DriverController@create
POST   /drivers                              → DriverController@store
GET    /drivers/{driver}/edit                 → DriverController@edit
PUT    /drivers/{driver}                      → DriverController@update
DELETE /drivers/{driver}                      → DriverController@destroy

GET    /drivers/{driver}/compensations        → DriverCompensationController@index
POST   /drivers/{driver}/compensations        → DriverCompensationController@store
```

---

## Authorization (Policies)

All policies extend `TenantPolicy` (Epic 1). Pattern per resource:

| Method | Admin | Operator | Financial |
|---|---|---|---|
| viewAny | ✅ | ✅ | ✅ |
| view | ✅ | ✅ | ✅ |
| create | ✅ | ✅ | ❌ |
| update | ✅ | ✅ | ❌ |
| delete | ✅ | ✅ | ❌ |

`DriverCompensationPolicy` mirrors `DriverPolicy`.

---

## Frontend Pages

### `Fleet/Vehicles/Index.vue`
- Table: plate, kind, type label, brand/model/year, active badge
- Filters: active/inactive toggle, plate search (debounced)
- Actions: Edit (Admin/Operator), Delete (Admin/Operator — soft), Create button

### `Fleet/Vehicles/Form.vue` (create + edit)
- Fields: kind (vehicle/trailer radio), vehicle_type (select filtered by kind), license_plate, renavam, brand, model, year, notes, active toggle
- Shared between create and edit via Inertia props

### `Fleet/Drivers/Index.vue`
- Table: name, CPF (masked), phone, active badge
- Filters: active/inactive toggle, name search
- Actions: Edit, Delete (soft), Compensations link, Create button

### `Fleet/Drivers/Form.vue` (create + edit)
- Fields: name, CPF (formatted input), phone, birth_date, active toggle

### `Fleet/Drivers/Compensations/Index.vue`
- **Active compensations section:** cards/table showing each active type with amount and `effective_from`
- **Add compensation form:** type selector (percentage / fixed_per_freight / monthly_salary) + dynamic amount field + effective_from date. Inline, no modal.
- **History table:** all past compensations ordered by `effective_from desc`, showing type, amount, effective_from, effective_to
- Financial role: read-only view, no add form rendered

---

## Testing Strategy

- **Feature tests per endpoint:** happy path + 401/403 for wrong role + tenant-leak test (second company cannot access first company's resources)
- **Policy tests:** Financial blocked on write operations; Operator allowed
- **Compensation tests:** creating second active of same type closes previous; two different types coexist; DB constraint rejects duplicate active via unique index
- **CPF validation test:** valid CPF passes, invalid CPF rejected, duplicate CPF within company rejected, same CPF in different company allowed
- All tests extend `TenantTestCase` (sets RLS context)

---

## Libraries to Install

```bash
composer require geekcom/validator-docs:^3.12 tighten/parental:^1
```

No new NPM packages required.
