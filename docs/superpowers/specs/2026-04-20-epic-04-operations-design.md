# Epic 4 — Operations: Freights & Lifecycle — Design Spec

## Goal

Build the freight creation wizard and full lifecycle state machine (ToStart → InRoute → Finished → AwaitingPayment → Completed), including cost capture at finish time and audit trail.

## Architecture

Modular monolith under `app/Modules/Operations/`. State machine via `spatie/laravel-model-states`. Thin controllers delegating to Action classes. Freight creation uses a 4-step Inertia wizard (Vue 3 Options API). Transitions happen on a dedicated show page via modal confirmations. Audit via `freight_status_history` append-only table + `spatie/laravel-activitylog`.

## Tech Stack

Laravel 11, Inertia.js, Vue 3 Options API, Tailwind, `spatie/laravel-model-states ^2`, `spatie/laravel-activitylog ^4`, PostgreSQL trigger for trailer enforcement.

---

## Schema

### `freights` table

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `company_id` | bigint FK | no | tenant scope |
| `client_id` | bigint FK | no | |
| `vehicle_id` | bigint FK | no | kind=vehicle only |
| `trailer_id` | bigint FK | yes | required when vehicle_type.requires_trailer |
| `driver_id` | bigint FK | yes | |
| `pricing_model` | enum(fixed, per_km) | no | |
| `fixed_rate_id` | bigint FK | yes | → fixed_freight_rates |
| `per_km_rate_id` | bigint FK | yes | → per_km_freight_rates |
| `origin` | string(150) | yes | per-km freights only |
| `destination` | string(150) | yes | per-km freights only |
| `distance_km` | decimal(10,2) | yes | entered at InRoute→Finished |
| `toll` | decimal(10,2) | yes | entered at InRoute→Finished |
| `fuel_price_per_liter` | decimal(8,4) | yes | entered at InRoute→Finished |
| `freight_value` | decimal(12,2) | yes | locked at Finished→AwaitingPayment |
| `status` | string | no | managed by spatie/laravel-model-states |
| `started_at` | timestamp | yes | set on ToStart→InRoute |
| `finished_at` | timestamp | yes | set on InRoute→Finished |
| `completed_at` | timestamp | yes | set on AwaitingPayment→Completed |

Soft deletes. Indexes: `(company_id, status)`, `(company_id, client_id)`, `(company_id, vehicle_id)`.

### `freight_status_history` table

Append-only audit. `freight_id`, `from_status` (nullable for first), `to_status`, `user_id`, `notes` (nullable), `occurred_at`. Hard delete (never soft-delete audit records).

### `vehicles` table — addition

Add `consumo_medio` decimal(8,2) nullable — fuel efficiency in km/L.

---

## State Machine

States: `ToStart`, `InRoute`, `Finished`, `AwaitingPayment`, `Completed`.

| Transition | Guard | Side Effects |
|---|---|---|
| ToStart → InRoute | none | sets `started_at = now()` |
| InRoute → Finished | `distance_km` required for per_km; `toll` required for per_km; fixed `toll` pre-fills from `FixedFreightRatePrice.tolls` | sets `finished_at = now()`; persists `distance_km`, `toll`, `fuel_price_per_liter` |
| Finished → AwaitingPayment | none | computes and locks `freight_value`; dispatches `FreightEnteredAwaitingPayment` event |
| AwaitingPayment → Completed | receivable must be `status=paid` (enforced in Epic 6) | sets `completed_at = now()` |

**`freight_value` computation:**
- Fixed: `FixedFreightRatePrice.price` where `fixed_freight_rate_id = freight.fixed_rate_id AND vehicle_type_id = freight.vehicle.vehicle_type_id`
- Per-km: `freight.distance_km × PerKmFreightRatePrice.rate_per_km` where `per_km_freight_rate_id = freight.per_km_rate_id AND vehicle_type_id = freight.vehicle.vehicle_type_id`

**Estimated fuel display (computed, not stored):**
- `estimated_liters = distance_km / vehicle.consumo_medio` (shown in Finished modal when both are set)
- `estimated_fuel_cost = estimated_liters × fuel_price_per_liter` (shown when all three values present)

---

## Trailer Enforcement — 3 Layers

1. **`StoreFreightRequest`**: validates `trailer_id` required/prohibited based on loaded vehicle's `vehicleType.requires_trailer`.
2. **`CreateFreightAction`**: re-checks the constraint before `Freight::create()` and throws if violated.
3. **PostgreSQL trigger** on `freights` INSERT/UPDATE: joins `vehicles → vehicle_types`, raises exception if `requires_trailer = true AND trailer_id IS NULL`.

---

## Wizard Steps

### Step 1 — Frete
Fields: `client_id` (searchable select), `pricing_model` (radio: Fixo / Por Km), `origin` + `destination` (text, shown only when `pricing_model = per_km`).

### Step 2 — Tarifa
- **Fixed**: select `ClientFreightTable` (filtered: client, pricing_model=fixed, active) → select `FixedFreightRate` within it. Shows route name + avg_km.
- **Per-km**: select BR state (27 options) → system resolves `PerKmFreightRate` for `(client_id, state)`. Error shown inline if none exists for that combination.

Data loaded via JSON endpoint: `GET /freights/rates?client_id=X&pricing_model=Y`

### Step 3 — Equipe
Fields: `vehicle_id` (active vehicles, kind=vehicle), `trailer_id` (visible + required when selected vehicle's `vehicleType.requires_trailer = true`, loads active trailers kind=trailer), `driver_id` (active drivers). Data passed as Inertia props from controller.

### Step 4 — Revisão
Read-only summary of all selections. Shows computed price preview:
- Fixed: `FixedFreightRatePrice.price` for the vehicle's type (if found)
- Per-km: "Calculado por km — taxa: R$ X,XXXX/km"

Submit creates freight in `ToStart`.

---

## Freight Show Page

Displays all freight fields grouped by section. Transition buttons shown per current status:

| Status | Available actions |
|---|---|
| ToStart | "Iniciar Frete" → InRoute |
| InRoute | "Finalizar Frete" → Finished (opens modal) |
| Finished | "Enviar para Pagamento" → AwaitingPayment |
| AwaitingPayment | (no action in Epic 4; Epic 6 handles payment) |
| Completed | — |

### InRoute → Finished modal fields

| Field | Per-km | Fixed |
|---|---|---|
| `distance_km` | Required | Optional |
| `toll` | Required | Pre-filled from rate (overridable) |
| `fuel_price_per_liter` | Optional | Optional |
| *Litros estimados* | Computed display | Computed display |
| *Custo combustível estimado* | Computed display | Computed display |

---

## Authorization (FreightPolicy)

- `viewAny`: Admin, Operator, Financial
- `view`: Admin, Operator, Financial + belongs to tenant
- `create`: Admin, Operator
- `update`: Admin, Operator + belongs to tenant
- `delete`: Admin only + belongs to tenant
- `transition`: Admin, Operator + belongs to tenant

---

## Module Structure

```
app/Modules/Operations/
  Actions/
    CreateFreightAction.php
    TransitionFreightAction.php
  Events/
    FreightEnteredAwaitingPayment.php
  Http/
    Controllers/
      FreightController.php
      FreightRatesController.php   (JSON endpoint for wizard step 2)
    Requests/
      StoreFreightRequest.php
      TransitionFreightRequest.php
  Listeners/
    CreateReceivableForFreight.php  (stub — implemented in Epic 6)
  Models/
    Freight.php
    FreightStatusHistory.php
  Observers/
    FreightObserver.php
  Policies/
    FreightPolicy.php
  States/
    FreightState.php               (abstract base)
    ToStart.php
    InRoute.php
    Finished.php
    AwaitingPayment.php
    Completed.php

resources/js/Pages/Operations/
  Index.vue
  Create.vue                       (4-step wizard)
  Show.vue
```

---

## Packages to Install

- `spatie/laravel-model-states ^2`
- `spatie/laravel-activitylog ^4`

---

## TODOs for Future Epics

- **Epic 5**: Pre-fill `fuel_records` from freight's `distance_km / consumo_medio` (estimated liters) and `fuel_price_per_liter`.
- **Epic 6**: `CreateReceivableForFreight` listener body + gate `AwaitingPayment → Completed` on `receivable.status = paid`.
