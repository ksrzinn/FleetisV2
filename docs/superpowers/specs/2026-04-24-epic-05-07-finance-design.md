# Epics 05 + 07 — Finance: Expenses, Fuel, Maintenance & Bills Design

**Date:** 2026-04-24
**Branch:** `epic/05-finance-expenses`
**Scope:** Epic 05 (Expenses, Fuel Records, Maintenance Records) + Epic 07 (Bills / Accounts Payable)

---

## Scope Split

| Epic | Tables | Key feature |
|------|--------|-------------|
| 05 | `expense_categories`, `expenses`, `fuel_records`, `maintenance_records` | Dynamic expense tag system |
| 07 | `bills`, `bill_installments` | Installment generation, recurring scheduler, business-day due dates |

Both epics share the same `Finance` module and are shipped in one branch.

---

## Database Schema

All tables: `id bigserial pk`, `company_id bigint not null FK`, composite index `(company_id, <lookup>)`, `created_at`, `updated_at`.

### [Epic 05] `expense_categories`
| Column | Type | Notes |
|--------|------|-------|
| `company_id` | bigint FK | tenant scope |
| `name` | varchar(100) | unique per company |
| `color` | char(7) | hex color, system-assigned from palette |

Seeded with defaults on company creation: "Combustível", "Pedágio", "Manutenção", "Seguro", "Administrativo".
New user-typed categories get the next color from a 12-color rotating palette (indexed by company count of categories).

### [Epic 05] `expenses`
| Column | Type | Notes |
|--------|------|-------|
| `company_id` | bigint FK | |
| `expense_category_id` | bigint FK | references `expense_categories` |
| `amount` | numeric(12,2) | |
| `incurred_on` | date | |
| `description` | text null | |
| `vehicle_id` | bigint null FK | |
| `freight_id` | bigint null FK | |

CHECK constraint: at most one of `vehicle_id`, `freight_id` non-null.

### [Epic 05] `fuel_records`
| Column | Type | Notes |
|--------|------|-------|
| `company_id` | bigint FK | |
| `vehicle_id` | bigint FK | required |
| `driver_id` | bigint null FK | |
| `freight_id` | bigint null FK | |
| `liters` | numeric(8,3) | |
| `price_per_liter` | numeric(8,4) | |
| `total_cost` | numeric(12,2) | computed and stored |
| `odometer_km` | integer | |
| `fueled_at` | date | |
| `station` | varchar(150) null | |

### [Epic 05] `maintenance_records`
| Column | Type | Notes |
|--------|------|-------|
| `company_id` | bigint FK | |
| `vehicle_id` | bigint FK | required |
| `type` | enum(`preventive`,`corrective`,`emergency`,`routine`) | |
| `description` | text | |
| `cost` | numeric(12,2) | |
| `odometer_km` | integer | |
| `performed_on` | date | |
| `provider` | varchar(150) null | |

### [Epic 07] `bills`
| Column | Type | Notes |
|--------|------|-------|
| `company_id` | bigint FK | |
| `supplier` | varchar(150) | |
| `description` | text null | |
| `bill_type` | enum(`one_time`,`recurring`,`installment`) | |
| `total_amount` | numeric(12,2) | |
| `due_date` | date | first due date / anchor date |
| `recurrence_cadence` | enum(`weekly`,`biweekly`,`monthly`,`yearly`) null | recurring + installment |
| `recurrence_day` | smallint(1–31) null | day of month for recurring generation; only used by `recurring` type |
| `recurrence_end` | date null | stop generating after this date; only used by `recurring` type |
| `installment_count` | smallint null | installment bills only |

**`total_amount` semantics by type:**
- `one_time` / `installment`: total across all installments (split evenly, remainder on last).
- `recurring`: per-installment amount (total is open-ended).

### [Epic 07] `bill_installments`
| Column | Type | Notes |
|--------|------|-------|
| `company_id` | bigint FK | |
| `bill_id` | bigint FK | |
| `sequence` | smallint | 1-based |
| `amount` | numeric(12,2) | |
| `due_date` | date | business-day adjusted |
| `paid_at` | timestamp null | |
| `paid_amount` | numeric(12,2) null | |

Payment is recorded via the existing `payments` table with `payable_type = 'bill_installment'`.

---

## Module Structure

### [Epic 05] Additions to `app/Modules/Finance/`

```
Models/
  ExpenseCategory.php
  Expense.php
  FuelRecord.php
  MaintenanceRecord.php

Http/Controllers/
  ExpenseCategoryController.php   — POST /expense-categories only (no management UI)
  ExpenseController.php           — index, create, store, edit, update, destroy
  FuelRecordController.php        — index, create, store, edit, update, destroy
  MaintenanceRecordController.php — index, create, store, edit, update, destroy

Http/Requests/
  StoreExpenseRequest.php
  UpdateExpenseRequest.php
  StoreFuelRecordRequest.php
  UpdateFuelRecordRequest.php
  StoreMaintenanceRecordRequest.php
  UpdateMaintenanceRecordRequest.php

Policies/
  ExpensePolicy.php
  FuelRecordPolicy.php
  MaintenanceRecordPolicy.php
```

No action layer for Epic 05 — all controller methods are thin CRUD without branching business logic.

### [Epic 07] Additions to `app/Modules/Finance/`

```
Models/
  Bill.php
  BillInstallment.php

Http/Controllers/
  BillController.php                    — index, create, store, show, edit, update, destroy
  BillInstallmentPaymentController.php  — store (record payment against installment)

Http/Requests/
  StoreBillRequest.php
  UpdateBillRequest.php
  StoreBillInstallmentPaymentRequest.php

Policies/
  BillPolicy.php

Actions/
  GenerateInstallmentsAction.php   — called from BillController::store for one_time/installment
  RecordBillPaymentAction.php      — called from BillInstallmentPaymentController::store

Services/
  BusinessDayCalculator.php        — resolves weekend + BR national holiday offsets via package

Console/Commands/
  GenerateRecurringInstallmentsCommand.php  — daily scheduler
```

---

## Business Logic

### [Epic 05] Expense Category Tag Flow

1. Frontend combobox lists existing `expense_categories` as colored chips.
2. User can select an existing category or type a new name.
3. On form submit: if the value is a new name (no matching id), the form first POSTs to `/expense-categories`.
4. Server creates the category with the next auto-assigned color from the palette, returns `{id, name, color}`.
5. Frontend shows the new chip as visual feedback, then submits the expense with the resolved `category_id`.

The color palette is a fixed 12-hex array; color index = `company_expense_categories_count % 12`.

### [Epic 05] Index Filters

| Resource | Filter params |
|----------|--------------|
| Expenses | `category_id`, `vehicle_id`, `freight_id`, `date_from`, `date_to` |
| Fuel records | `vehicle_id`, `driver_id`, `freight_id`, `date_from`, `date_to` |
| Maintenance | `vehicle_id`, `type`, `date_from`, `date_to` |

All filters use `spatie/laravel-query-builder` (already installed), consistent with existing indexes.

### [Epic 07] Installment Generation on Bill Store

| `bill_type` | Behavior |
|-------------|----------|
| `one_time` | 1 installment created, `due_date` = `bill.due_date`, business-day adjusted |
| `installment` | N installments created upfront; amounts split evenly (remainder on last); due dates stepped by `recurrence_cadence` starting from `bill.due_date`; each date business-day adjusted |
| `recurring` | 1 installment created for first occurrence; subsequent ones generated by daily scheduler |

### [Epic 07] Business-Day Adjustment

`BusinessDayCalculator::nextBusinessDay(date)`:
1. If date is a weekend → advance to Monday.
2. If date is a Brazilian national holiday (resolved via package, evaluated per year) → advance by 1 day.
3. Repeat until the result is neither weekend nor holiday.

Package to evaluate at implementation time: `spatie/holidays` (supports Brazil, year-aware).

### [Epic 07] Recurring Installment Scheduler

`GenerateRecurringInstallmentsCommand` runs daily (registered in `routes/console.php`).

For each active recurring bill (no `recurrence_end` or `recurrence_end` >= today):
- Determine the next expected generation date: last installment's `due_date` + cadence.
- If that date is today or in the past and no installment exists for that period → generate it.
- Business-day adjust the new `due_date`.
- Stop if `recurrence_end` is set and next date > `recurrence_end`.

### [Epic 07] Bill Payment Recording

- Payment recorded against `bill_installment` via existing `payments` table (`payable_type = 'bill_installment'`, `payable_id = installment.id`).
- Partial payment supported: `installment.paid_amount` accumulates.
- Installment marked paid (`paid_at` set) when `paid_amount >= amount`.

### [Epic 07] Progress Display

| `bill_type` | Index table | Detail view |
|-------------|-------------|-------------|
| `installment` | `3/10` (paid/total) | Full installment list with status chips |
| `recurring` | `3/∞` with tooltip "Parcelas geradas até hoje" | Full list of generated installments |
| `one_time` | Paid/Unpaid badge only | Single installment row |

---

## Roles & Policies

Consistent with existing module role split:

| Action | Admin | Financial | Operator |
|--------|-------|-----------|----------|
| View expenses/fuel/maintenance/bills | ✓ | ✓ | ✗ |
| Create/edit/delete all above | ✓ | ✓ | ✗ |
| Record bill/installment payments | ✓ | ✓ | ✗ |

---

## Frontend Pages

### [Epic 05]
- `Finance/Expenses/Index.vue` — filterable table, category chips with colors
- `Finance/Expenses/Form.vue` — shared create/edit, category combobox with AJAX
- `Finance/FuelRecords/Index.vue` — filterable table
- `Finance/FuelRecords/Form.vue` — shared create/edit
- `Finance/Maintenance/Index.vue` — filterable table
- `Finance/Maintenance/Form.vue` — shared create/edit

### [Epic 07]
- `Finance/Bills/Index.vue` — table with installment progress counter
- `Finance/Bills/Form.vue` — shared create/edit, conditional fields per `bill_type`
- `Finance/Bills/Show.vue` — detail view with installment list and payment recording

---

## Verification

- Feature tests per controller (happy path + tenant-leak test per index/show).
- Policy tests: Operator cannot access any Finance write endpoint.
- Unit test for `BusinessDayCalculator` covering: weekend rollover, holiday rollover, chain (holiday on Monday after weekend).
- Unit test for `GenerateInstallmentsAction`: installment count, amount split, due date stepping.
- Unit test for recurring scheduler: generates on correct day, stops at `recurrence_end`, skips already-generated.
