# Epic 07 — Finance: Accounts Payable / Bills

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Branch:** `epic/05-finance-expenses` (shared with Epic 05)

**Goal:** Build the Finance: Accounts Payable module — Bills CRUD with three types (one-time, installment, recurring), automatic installment generation, installment payment recording via the shared `payments` table, and a daily scheduler that generates the next installment for recurring bills as each one comes due. Business-day adjustment (weekends + Brazilian holidays) applied to all computed due dates via `BusinessDayCalculator`.

**Architecture:** `GenerateInstallmentsAction` owns installment-generation logic for all three bill types, including the first installment for recurring bills. The daily scheduler generates subsequent recurring installments using a "generate when due" rule (`last_due_date + cadence <= today`). Payment recording is handled by `RecordBillPaymentAction`. `BillInstallment.status` is a **computed PHP property** (not a stored DB column): derived from `paid_amount` vs `amount` and `due_date` vs today. Soft deletes on `bills` with cascade; blocked when any installment has payments.

**Tech Stack:** Laravel 11, Inertia.js, Vue 3 Options API, PostgreSQL, spatie/laravel-permission (roles: Admin, Financial), spatie/holidays (Brazilian business-day adjustment), TailwindCSS.

---

## Architectural Decisions

| Decision | Choice | Reason |
|---|---|---|
| Morph map for payments | No morph map — plain string `'bill_installment'` | Consistent with existing `'receivable'` pattern; no `Relation::morphMap` registered |
| `bill_installments.status` | Computed PHP property (`$appends`) | No stored status to go stale; computed from `paid_amount` vs `amount` and `due_date` vs today |
| `paid_amount` column | Nullable decimal (null = unpaid) | Design doc; nullable signals "nothing paid" distinctly from 0.00 |
| Recurring generation | **1 installment on store**; scheduler generates next when `last_due_date + cadence <= today` | First installment visible immediately; scheduler is event-driven rather than lookahead |
| `total_amount` for recurring | Per-installment amount (not cumulative total) | End is open-ended; total is undefined |
| Cadences | `weekly`, `biweekly`, `monthly`, `yearly` | Full cadence set per design doc |
| Business-day adjustment | `BusinessDayCalculator` wrapping `spatie/holidays` (BR) | Avoids due dates on weekends/Brazilian holidays |
| `recurrence_day` | Monthly/yearly: 1–28 (clamped day-of-month); weekly/biweekly: not used (just add days from prior) | Matches `DueDateCalculator` convention |
| Action naming | `GenerateInstallmentsAction`, `RecordBillPaymentAction` | Per design doc naming |
| Soft delete on bills | Yes, with cascade; block if any installment has payments | Audit integrity |
| Role access | Admin + Financial full CRUD. **Operator: no access** | Finance-only domain per design doc |
| Progress display | `3/10` installment bills; `3/∞` recurring without end | Communicates open-ended nature of recurring |

---

## File Map

### New files
| Path | Responsibility |
|---|---|
| `database/migrations/2026_04_26_000001_create_bills_table.php` | Bills schema + soft deletes |
| `database/migrations/2026_04_26_000002_create_bill_installments_table.php` | Installments schema + unique (bill_id, due_date) |
| `database/migrations/rls/2026_04_26_000003_enable_rls_on_bill_tables.php` | RLS on bills + bill_installments |
| `database/factories/Finance/BillFactory.php` | Test factory |
| `database/factories/Finance/BillInstallmentFactory.php` | Test factory |
| `app/Modules/Finance/Models/Bill.php` | BelongsToCompany, SoftDeletes, hasMany installments |
| `app/Modules/Finance/Models/BillInstallment.php` | BelongsToCompany, belongsTo Bill, morphMany payments, computed status |
| `app/Modules/Finance/Policies/BillPolicy.php` | Admin + Financial only |
| `app/Modules/Finance/Services/BusinessDayCalculator.php` | Adjusts dates past weekends/BR holidays; computes next cadence date |
| `app/Modules/Finance/Actions/GenerateInstallmentsAction.php` | Creates bill + generates initial installments for all three types |
| `app/Modules/Finance/Actions/UpdateBillAction.php` | Updates bill metadata when safe |
| `app/Modules/Finance/Actions/RecordBillPaymentAction.php` | Records payment, updates paid_amount + paid_at |
| `app/Modules/Finance/Http/Requests/StoreBillRequest.php` | Validates bill creation including type-conditional fields |
| `app/Modules/Finance/Http/Requests/UpdateBillRequest.php` | Validates bill update |
| `app/Modules/Finance/Http/Requests/StoreBillPaymentRequest.php` | Validates installment payment |
| `app/Modules/Finance/Http/Controllers/BillController.php` | index, create, store, show, edit, update, destroy |
| `app/Modules/Finance/Http/Controllers/BillPaymentController.php` | store only |
| `app/Console/Commands/Finance/GenerateRecurringBillInstallmentsCommand.php` | Daily scheduler: generate next installment when due |
| `resources/js/Pages/Finance/Bills/Index.vue` | Bills list with type/supplier filters; progress column |
| `resources/js/Pages/Finance/Bills/Form.vue` | Create/edit form with dynamic field sets per type |
| `resources/js/Pages/Finance/Bills/Show.vue` | Bill detail: installments table + payment recording |
| `tests/Feature/Finance/BillControllerTest.php` | index, create, show, update, destroy, role gates, tenant isolation |
| `tests/Feature/Finance/GenerateInstallmentsActionTest.php` | Installment generation for all three types |
| `tests/Feature/Finance/RecordBillPaymentTest.php` | Payment logic, paid_amount accumulation, paid_at |
| `tests/Feature/Finance/GenerateRecurringInstallmentsTest.php` | Scheduler: generate-when-due, idempotency, end date stop |

### Modified files
| Path | Change |
|---|---|
| `routes/web.php` | Add bills resource + installment payment route |
| `routes/console.php` | Schedule `finance:generate-recurring-installments` daily |
| `app/Providers/AppServiceProvider.php` | Register BillPolicy |

---

## Task 0: Install required packages

- [ ] **Step 1: Install spatie/holidays**

```bash
composer require spatie/holidays
```

Expected: Package installed, no conflicts.

- [ ] **Step 2: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: install spatie/holidays for Brazilian business-day adjustment"
```

---

## Task 1: Migrations

**Files:**
- Create: `database/migrations/2026_04_26_000001_create_bills_table.php`
- Create: `database/migrations/2026_04_26_000002_create_bill_installments_table.php`
- Create: `database/migrations/rls/2026_04_26_000003_enable_rls_on_bill_tables.php`

- [ ] **Step 1: Create bills migration**

```php
<?php
// database/migrations/2026_04_26_000001_create_bills_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('supplier');
            $table->text('description')->nullable();
            $table->enum('bill_type', ['one_time', 'recurring', 'installment']);
            $table->decimal('total_amount', 12, 2);
            $table->date('due_date');
            // recurring + installment fields
            $table->enum('recurrence_cadence', ['weekly', 'biweekly', 'monthly', 'yearly'])->nullable();
            $table->tinyInteger('recurrence_day')->nullable();
            $table->date('recurrence_end')->nullable();
            // installment-only field
            $table->unsignedSmallInteger('installment_count')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'bill_type']);
            $table->index(['company_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
```

- [ ] **Step 2: Create bill_installments migration**

```php
<?php
// database/migrations/2026_04_26_000002_create_bill_installments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['bill_id', 'due_date']);
            $table->index(['company_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_installments');
    }
};
```

- [ ] **Step 3: Create RLS migration**

```php
<?php
// database/migrations/rls/2026_04_26_000003_enable_rls_on_bill_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE bills ENABLE ROW LEVEL SECURITY;
            ALTER TABLE bills FORCE ROW LEVEL SECURITY;
            CREATE POLICY bills_company_isolation ON bills
                USING (
                    current_setting('app.current_company_id', true) IS NULL
                    OR current_setting('app.current_company_id', true) = ''
                    OR company_id = current_setting('app.current_company_id', true)::bigint
                )
                WITH CHECK (
                    current_setting('app.current_company_id', true) IS NULL
                    OR current_setting('app.current_company_id', true) = ''
                    OR company_id = current_setting('app.current_company_id', true)::bigint
                );

            ALTER TABLE bill_installments ENABLE ROW LEVEL SECURITY;
            ALTER TABLE bill_installments FORCE ROW LEVEL SECURITY;
            CREATE POLICY bill_installments_company_isolation ON bill_installments
                USING (
                    current_setting('app.current_company_id', true) IS NULL
                    OR current_setting('app.current_company_id', true) = ''
                    OR company_id = current_setting('app.current_company_id', true)::bigint
                )
                WITH CHECK (
                    current_setting('app.current_company_id', true) IS NULL
                    OR current_setting('app.current_company_id', true) = ''
                    OR company_id = current_setting('app.current_company_id', true)::bigint
                );
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP POLICY IF EXISTS bills_company_isolation ON bills;
            DROP POLICY IF EXISTS bill_installments_company_isolation ON bill_installments;
            ALTER TABLE bills DISABLE ROW LEVEL SECURITY;
            ALTER TABLE bill_installments DISABLE ROW LEVEL SECURITY;
        SQL);
    }
};
```

- [ ] **Step 4: Run migrations**

```bash
php artisan migrate
```

Expected: No errors. Tables `bills`, `bill_installments` created.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_26_000001_create_bills_table.php \
        database/migrations/2026_04_26_000002_create_bill_installments_table.php \
        database/migrations/rls/2026_04_26_000003_enable_rls_on_bill_tables.php
git commit -m "feat(finance): add bills and bill_installments migrations with RLS"
```

---

## Task 2: Models and Factories

- [ ] **Step 1: Create Bill model**

```php
<?php
// app/Modules/Finance/Models/Bill.php

namespace App\Modules\Finance\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Finance\BillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'supplier', 'description', 'bill_type', 'total_amount',
        'due_date', 'recurrence_cadence', 'recurrence_day', 'recurrence_end',
        'installment_count',
    ];

    protected $casts = [
        'total_amount'   => 'decimal:2',
        'due_date'       => 'date',
        'recurrence_end' => 'date',
    ];

    protected static function newFactory(): BillFactory
    {
        return BillFactory::new();
    }

    /** @return HasMany<BillInstallment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(BillInstallment::class)->orderBy('due_date');
    }

    public function hasPayments(): bool
    {
        return $this->installments()->whereNotNull('paid_amount')->exists();
    }

    public function outstandingBalance(): string
    {
        $totalAmount = (string) $this->installments()->sum('amount');
        $totalPaid   = (string) $this->installments()->sum('paid_amount');
        return bcsub($totalAmount, $totalPaid, 2);
    }
}
```

- [ ] **Step 2: Create BillInstallment model**

```php
<?php
// app/Modules/Finance/Models/BillInstallment.php

namespace App\Modules\Finance\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Finance\BillInstallmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillInstallment extends Model
{
    /** @use HasFactory<BillInstallmentFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'bill_id', 'sequence', 'amount',
        'due_date', 'paid_amount', 'paid_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date'    => 'date',
        'paid_at'     => 'datetime',
    ];

    protected $appends = ['status'];

    protected static function newFactory(): BillInstallmentFactory
    {
        return BillInstallmentFactory::new();
    }

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payable_id')
            ->where('payable_type', 'bill_installment')
            ->orderBy('paid_at');
    }

    public function getStatusAttribute(): string
    {
        $paid  = (float) ($this->paid_amount ?? 0);
        $total = (float) $this->amount;

        if ($paid >= $total) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partially_paid';
        }

        if ($this->due_date->isPast()) {
            return 'overdue';
        }

        return 'open';
    }

    public function isFullyPaid(): bool
    {
        return $this->status === 'paid';
    }
}
```

- [ ] **Step 3: Create BillFactory**

```php
<?php
// database/factories/Finance/BillFactory.php

namespace Database\Factories\Finance;

use App\Modules\Finance\Models\Bill;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Bill> */
class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        return [
            'company_id'         => Company::factory(),
            'supplier'           => fake()->company(),
            'description'        => fake()->sentence(),
            'bill_type'          => 'one_time',
            'total_amount'       => fake()->randomFloat(2, 100, 5000),
            'due_date'           => now()->addDays(30)->toDateString(),
            'recurrence_cadence' => null,
            'recurrence_day'     => null,
            'recurrence_end'     => null,
            'installment_count'  => null,
        ];
    }

    public function recurring(string $cadence = 'monthly'): static
    {
        return $this->state([
            'bill_type'          => 'recurring',
            'recurrence_cadence' => $cadence,
            'recurrence_day'     => 10,
            'recurrence_end'     => now()->addYear()->toDateString(),
        ]);
    }

    public function installment(int $count = 3): static
    {
        return $this->state([
            'bill_type'          => 'installment',
            'installment_count'  => $count,
            'recurrence_cadence' => 'monthly',
            'recurrence_day'     => now()->addMonth()->day,
        ]);
    }
}
```

- [ ] **Step 4: Create BillInstallmentFactory**

```php
<?php
// database/factories/Finance/BillInstallmentFactory.php

namespace Database\Factories\Finance;

use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillInstallment;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BillInstallment> */
class BillInstallmentFactory extends Factory
{
    protected $model = BillInstallment::class;

    public function definition(): array
    {
        $company = Company::factory()->create();
        $bill    = Bill::factory()->create(['company_id' => $company->id]);

        return [
            'company_id'  => $company->id,
            'bill_id'     => $bill->id,
            'sequence'    => 1,
            'amount'      => fake()->randomFloat(2, 100, 2000),
            'due_date'    => now()->addDays(30)->toDateString(),
            'paid_amount' => null,
            'paid_at'     => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attrs) => [
            'paid_amount' => $attrs['amount'],
            'paid_at'     => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'due_date'    => now()->subDays(5)->toDateString(),
            'paid_amount' => null,
        ]);
    }
}
```

- [ ] **Step 5: Run full test suite**

```bash
php artisan test
```

Expected: All passing.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Finance/Models/Bill.php \
        app/Modules/Finance/Models/BillInstallment.php \
        database/factories/Finance/BillFactory.php \
        database/factories/Finance/BillInstallmentFactory.php
git commit -m "feat(finance): add Bill and BillInstallment models with factories"
```

---

## Task 3: BillPolicy + Registration

- [ ] **Step 1: Create BillPolicy**

```php
<?php
// app/Modules/Finance/Policies/BillPolicy.php

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillInstallment;

class BillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function view(User $user, Bill $bill): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $bill->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function update(User $user, Bill $bill): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $bill->company_id;
    }

    public function delete(User $user, Bill $bill): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $bill->company_id;
    }

    public function recordPayment(User $user, BillInstallment $installment): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $installment->company_id;
    }
}
```

- [ ] **Step 2: Register in AppServiceProvider**

Add to `app/Providers/AppServiceProvider.php`:

```php
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Policies\BillPolicy;

// in boot():
Gate::policy(Bill::class, BillPolicy::class);
```

- [ ] **Step 3: Run full test suite**

```bash
php artisan test
```

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Finance/Policies/BillPolicy.php \
        app/Providers/AppServiceProvider.php
git commit -m "feat(finance): add BillPolicy (Admin + Financial only)"
```

---

## Task 4: BusinessDayCalculator service

Handles due-date computation respecting weekends and Brazilian public holidays via `spatie/holidays`.

- [ ] **Step 1: Create BusinessDayCalculator**

```php
<?php
// app/Modules/Finance/Services/BusinessDayCalculator.php

namespace App\Modules\Finance\Services;

use Illuminate\Support\Carbon;
use Spatie\Holidays\Holidays;

class BusinessDayCalculator
{
    public function nextDate(Carbon $from, string $cadence, int $recurrenceDay): Carbon
    {
        $next = match ($cadence) {
            'weekly'   => $from->copy()->addWeek(),
            'biweekly' => $from->copy()->addWeeks(2),
            'monthly'  => $from->copy()->addMonthNoOverflow()->setDay(min($recurrenceDay, 28)),
            'yearly'   => $from->copy()->addYear()->setDay(min($recurrenceDay, 28)),
            default    => $from->copy()->addMonth(),
        };

        return $this->adjustToBusinessDay($next);
    }

    public function adjustToBusinessDay(Carbon $date): Carbon
    {
        $holidays = $this->getHolidays($date->year);

        while ($date->isWeekend() || $this->isHoliday($date, $holidays)) {
            $date->addDay();
        }

        return $date;
    }

    /** @param array<string> $holidays ISO date strings */
    private function isHoliday(Carbon $date, array $holidays): bool
    {
        return in_array($date->toDateString(), $holidays, true);
    }

    /** @return array<string> */
    private function getHolidays(int $year): array
    {
        return Holidays::for(country: 'br')
            ->get(year: $year)
            ->map(fn (Carbon $h) => $h->toDateString())
            ->toArray();
    }
}
```

- [ ] **Step 2: Run full test suite**

```bash
php artisan test
```

- [ ] **Step 3: Commit**

```bash
git add app/Modules/Finance/Services/BusinessDayCalculator.php
git commit -m "feat(finance): add BusinessDayCalculator with spatie/holidays"
```

---

## Task 5: GenerateInstallmentsAction (TDD)

Creates the bill and generates installments. Recurring bills get **1 installment** on store (the first one). Subsequent installments are generated by the scheduler.

**Files:**
- Create: `tests/Feature/Finance/GenerateInstallmentsActionTest.php`
- Create: `app/Modules/Finance/Actions/GenerateInstallmentsAction.php`
- Create: `app/Modules/Finance/Actions/UpdateBillAction.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/Finance/GenerateInstallmentsActionTest.php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Actions\GenerateInstallmentsAction;
use App\Modules\Finance\Models\Bill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class GenerateInstallmentsActionTest extends TenantTestCase
{
    use RefreshDatabase;

    private GenerateInstallmentsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(GenerateInstallmentsAction::class);
    }

    // ── one_time ─────────────────────────────────────────────────────────────

    public function test_one_time_bill_creates_single_installment(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = $this->action->handle([
            'supplier'     => 'Fornecedor X',
            'bill_type'    => 'one_time',
            'total_amount' => '1200.00',
            'due_date'     => '2026-05-15',
        ]);

        $this->assertInstanceOf(Bill::class, $bill);
        $this->assertCount(1, $bill->installments);

        $installment = $bill->installments->first();
        $this->assertEquals('1200.00', $installment->amount);
        $this->assertEquals('2026-05-15', $installment->due_date->toDateString());
        $this->assertEquals(1, $installment->sequence);
        $this->assertEquals('open', $installment->status);
        $this->assertNull($installment->paid_amount);
    }

    // ── installment ───────────────────────────────────────────────────────────

    public function test_installment_bill_generates_all_n_installments(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = $this->action->handle([
            'supplier'           => 'Fornecedor Y',
            'bill_type'          => 'installment',
            'total_amount'       => '900.00',
            'due_date'           => '2026-05-10',
            'installment_count'  => 3,
            'recurrence_cadence' => 'monthly',
            'recurrence_day'     => 10,
        ]);

        $this->assertCount(3, $bill->installments);
        $this->assertEquals(1, $bill->installments[0]->sequence);
        $this->assertEquals(2, $bill->installments[1]->sequence);
        $this->assertEquals(3, $bill->installments[2]->sequence);
    }

    public function test_installment_bill_splits_amount_evenly_with_remainder_on_last(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = $this->action->handle([
            'supplier'           => 'Fornecedor Z',
            'bill_type'          => 'installment',
            'total_amount'       => '1000.00',
            'due_date'           => '2026-05-10',
            'installment_count'  => 3,
            'recurrence_cadence' => 'monthly',
            'recurrence_day'     => 10,
        ]);

        $amounts = $bill->installments->pluck('amount')->map(fn ($a) => (float) $a);
        $this->assertEquals(1000.0, $amounts->sum());
        $this->assertEquals(333.33, $amounts[0]);
        $this->assertEquals(333.33, $amounts[1]);
        $this->assertEquals(333.34, $amounts[2]);
    }

    public function test_installment_bill_spaces_due_dates_monthly(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = $this->action->handle([
            'supplier'           => 'Fornecedor W',
            'bill_type'          => 'installment',
            'total_amount'       => '600.00',
            'due_date'           => '2026-05-10',
            'installment_count'  => 3,
            'recurrence_cadence' => 'monthly',
            'recurrence_day'     => 10,
        ]);

        $dates = $bill->installments->pluck('due_date')->map(fn ($d) => $d->toDateString());
        $this->assertEquals('2026-05-10', $dates[0]);
        $this->assertEquals('2026-06-10', $dates[1]);
        $this->assertEquals('2026-07-10', $dates[2]);
    }

    // ── recurring ─────────────────────────────────────────────────────────────

    public function test_recurring_bill_creates_one_installment_on_store(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = $this->action->handle([
            'supplier'           => 'Fornecedor Recorrente',
            'bill_type'          => 'recurring',
            'total_amount'       => '500.00',
            'due_date'           => '2026-05-01',
            'recurrence_cadence' => 'monthly',
            'recurrence_day'     => 1,
            'recurrence_end'     => '2027-05-01',
        ]);

        $this->assertCount(1, $bill->installments);
        $installment = $bill->installments->first();
        $this->assertEquals('500.00', $installment->amount);
        $this->assertEquals('2026-05-01', $installment->due_date->toDateString());
        $this->assertEquals(1, $installment->sequence);
    }

    public function test_recurring_bill_total_amount_is_per_installment(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = $this->action->handle([
            'supplier'           => 'Locação Escritório',
            'bill_type'          => 'recurring',
            'total_amount'       => '3000.00',
            'due_date'           => '2026-05-05',
            'recurrence_cadence' => 'monthly',
            'recurrence_day'     => 5,
        ]);

        $installment = $bill->installments->first();
        $this->assertEquals('3000.00', $installment->amount);
    }
}
```

- [ ] **Step 2: Run tests — expect failure**

```bash
php artisan test tests/Feature/Finance/GenerateInstallmentsActionTest.php
```

Expected: FAIL — `GenerateInstallmentsAction` class not found.

- [ ] **Step 3: Implement GenerateInstallmentsAction**

```php
<?php
// app/Modules/Finance/Actions/GenerateInstallmentsAction.php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Services\BusinessDayCalculator;
use Illuminate\Support\Carbon;

class GenerateInstallmentsAction
{
    public function __construct(private BusinessDayCalculator $calculator) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data): Bill
    {
        $bill = Bill::create($data);

        match ($bill->bill_type) {
            'one_time'    => $this->generateOneTime($bill),
            'installment' => $this->generateInstallments($bill),
            'recurring'   => $this->generateFirstRecurring($bill),
        };

        return $bill->load('installments');
    }

    private function generateOneTime(Bill $bill): void
    {
        $bill->installments()->create([
            'company_id' => $bill->company_id,
            'sequence'   => 1,
            'amount'     => $bill->total_amount,
            'due_date'   => $bill->due_date,
        ]);
    }

    private function generateFirstRecurring(Bill $bill): void
    {
        $due = $this->calculator->adjustToBusinessDay($bill->due_date->copy());

        $bill->installments()->create([
            'company_id' => $bill->company_id,
            'sequence'   => 1,
            'amount'     => $bill->total_amount,
            'due_date'   => $due->toDateString(),
        ]);
    }

    private function generateInstallments(Bill $bill): void
    {
        $n         = (int) $bill->installment_count;
        $base      = bcdiv((string) $bill->total_amount, (string) $n, 2);
        $total     = bcmul($base, (string) $n, 2);
        $remainder = bcsub((string) $bill->total_amount, $total, 2);

        $dueDate  = $bill->due_date->copy();
        $cadence  = $bill->recurrence_cadence;
        $day      = (int) $bill->recurrence_day;

        for ($i = 1; $i <= $n; $i++) {
            $amount = ($i === $n) ? bcadd($base, $remainder, 2) : $base;

            $adjusted = $this->calculator->adjustToBusinessDay($dueDate->copy());

            $bill->installments()->create([
                'company_id' => $bill->company_id,
                'sequence'   => $i,
                'amount'     => $amount,
                'due_date'   => $adjusted->toDateString(),
            ]);

            $dueDate = $this->calculator->nextDate($dueDate, $cadence, $day);
        }
    }
}
```

- [ ] **Step 4: Create UpdateBillAction**

```php
<?php
// app/Modules/Finance/Actions/UpdateBillAction.php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Bill;

class UpdateBillAction
{
    /** @param array<string, mixed> $data */
    public function handle(Bill $bill, array $data): Bill
    {
        if ($bill->hasPayments()) {
            unset(
                $data['total_amount'],
                $data['installment_count'],
                $data['recurrence_cadence'],
                $data['recurrence_day']
            );
        }

        $bill->update($data);

        return $bill;
    }
}
```

- [ ] **Step 5: Run tests — expect pass**

```bash
php artisan test tests/Feature/Finance/GenerateInstallmentsActionTest.php
```

Expected: All passing.

- [ ] **Step 6: Run full test suite**

```bash
php artisan test
```

Expected: All passing.

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Finance/Actions/GenerateInstallmentsAction.php \
        app/Modules/Finance/Actions/UpdateBillAction.php \
        tests/Feature/Finance/GenerateInstallmentsActionTest.php
git commit -m "feat(finance): implement GenerateInstallmentsAction with business-day adjustment"
```

---

## Task 6: Bill CRUD Controller (TDD)

**Files:**
- Create: `tests/Feature/Finance/BillControllerTest.php`
- Create: `app/Modules/Finance/Http/Requests/StoreBillRequest.php`
- Create: `app/Modules/Finance/Http/Requests/UpdateBillRequest.php`
- Create: `app/Modules/Finance/Http/Controllers/BillController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/Finance/BillControllerTest.php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class BillControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    // ── Index ────────────────────────────────────────────────────────────────

    public function test_financial_can_access_bills_index(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $this->actingAsTenant($user)->get('/bills')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Finance/Bills/Index'));
    }

    public function test_operator_cannot_access_bills(): void
    {
        $user = $this->makeUserWithRole('Operator');

        $this->actingAsTenant($user)->get('/bills')->assertForbidden();
    }

    public function test_index_only_returns_own_company_bills(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        Bill::factory()->create(['company_id' => $userA->company_id]);
        Bill::factory()->create(['company_id' => $userA->company_id]);
        Bill::factory()->create(['company_id' => $userB->company_id]);

        $this->actingAsTenant($userA)->get('/bills')
            ->assertInertia(fn ($page) => $page->has('bills.data', 2));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_financial_can_create_one_time_bill(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $response = $this->actingAsTenant($user)->post('/bills', [
            'supplier'     => 'Aluguel',
            'bill_type'    => 'one_time',
            'total_amount' => '2500.00',
            'due_date'     => '2026-05-10',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bills', [
            'company_id' => $user->company_id,
            'supplier'   => 'Aluguel',
            'bill_type'  => 'one_time',
        ]);
        $this->assertDatabaseHas('bill_installments', [
            'company_id' => $user->company_id,
            'amount'     => '2500.00',
        ]);
    }

    public function test_operator_cannot_create_bill(): void
    {
        $user = $this->makeUserWithRole('Operator');

        $this->actingAsTenant($user)->post('/bills', [
            'supplier'     => 'Aluguel',
            'bill_type'    => 'one_time',
            'total_amount' => '1000.00',
            'due_date'     => '2026-05-01',
        ])->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_financial_can_view_bill(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $bill = Bill::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->get("/bills/{$bill->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Bills/Show')
                ->has('bill')
                ->has('bill.installments')
            );
    }

    public function test_financial_cannot_view_other_company_bill(): void
    {
        $user  = $this->makeUserWithRole('Financial');
        $other = Bill::factory()->create();

        $this->actingAsTenant($user)->get("/bills/{$other->id}")->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_financial_can_update_bill_supplier(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $bill = Bill::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->put("/bills/{$bill->id}", [
            'supplier'     => 'Novo Fornecedor',
            'bill_type'    => $bill->bill_type,
            'total_amount' => $bill->total_amount,
            'due_date'     => $bill->due_date->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'supplier' => 'Novo Fornecedor']);
    }

    public function test_financial_cannot_update_other_company_bill(): void
    {
        $user  = $this->makeUserWithRole('Financial');
        $other = Bill::factory()->create();

        $this->actingAsTenant($user)->put("/bills/{$other->id}", [
            'supplier'     => 'Hack',
            'bill_type'    => 'one_time',
            'total_amount' => '1.00',
            'due_date'     => '2026-01-01',
        ])->assertForbidden();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_financial_can_delete_bill_without_payments(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $bill = Bill::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->delete("/bills/{$bill->id}")
            ->assertRedirect('/bills');
        $this->assertSoftDeleted('bills', ['id' => $bill->id]);
    }

    public function test_financial_cannot_delete_bill_with_payments(): void
    {
        $user        = $this->makeUserWithRole('Financial');
        $bill        = Bill::factory()->create(['company_id' => $user->company_id]);
        $installment = BillInstallment::factory()->create([
            'company_id'  => $user->company_id,
            'bill_id'     => $bill->id,
            'paid_amount' => '100.00',
        ]);

        $this->actingAsTenant($user)->delete("/bills/{$bill->id}")
            ->assertForbidden();
    }

    public function test_operator_cannot_delete_bill(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $bill = Bill::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->delete("/bills/{$bill->id}")->assertForbidden();
    }
}
```

- [ ] **Step 2: Run tests — expect failure**

```bash
php artisan test tests/Feature/Finance/BillControllerTest.php
```

- [ ] **Step 3: Add routes to web.php**

Inside the auth+tenant middleware group:

```php
use App\Modules\Finance\Http\Controllers\BillController;
use App\Modules\Finance\Http\Controllers\BillPaymentController;

Route::resource('bills', BillController::class);
Route::post('bill-installments/{installment}/payments', [BillPaymentController::class, 'store'])
    ->name('bill-installments.payments.store');
```

- [ ] **Step 4: Create StoreBillRequest**

```php
<?php
// app/Modules/Finance/Http/Requests/StoreBillRequest.php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $cadences = ['weekly', 'biweekly', 'monthly', 'yearly'];

        return [
            'supplier'           => ['required', 'string', 'max:200'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'bill_type'          => ['required', Rule::in(['one_time', 'recurring', 'installment'])],
            'total_amount'       => ['required', 'numeric', 'min:0.01'],
            'due_date'           => ['required', 'date'],
            'recurrence_cadence' => [
                Rule::requiredIf(fn () => in_array($this->input('bill_type'), ['recurring', 'installment'])),
                'nullable',
                Rule::in($cadences),
            ],
            'recurrence_day'     => [
                Rule::requiredIf(fn () => in_array($this->input('bill_type'), ['recurring', 'installment'])
                    && in_array($this->input('recurrence_cadence'), ['monthly', 'yearly'])),
                'nullable',
                'integer',
                'min:1',
                'max:28',
            ],
            'recurrence_end'     => ['nullable', 'date', 'after:due_date'],
            'installment_count'  => [
                Rule::requiredIf(fn () => $this->input('bill_type') === 'installment'),
                'nullable',
                'integer',
                'min:2',
                'max:360',
            ],
        ];
    }
}
```

- [ ] **Step 5: Create UpdateBillRequest**

```php
<?php
// app/Modules/Finance/Http/Requests/UpdateBillRequest.php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'supplier'           => ['required', 'string', 'max:200'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'bill_type'          => ['required', Rule::in(['one_time', 'recurring', 'installment'])],
            'total_amount'       => ['required', 'numeric', 'min:0.01'],
            'due_date'           => ['required', 'date'],
            'recurrence_cadence' => ['nullable', Rule::in(['weekly', 'biweekly', 'monthly', 'yearly'])],
            'recurrence_day'     => ['nullable', 'integer', 'min:1', 'max:28'],
            'recurrence_end'     => ['nullable', 'date'],
            'installment_count'  => ['nullable', 'integer', 'min:2', 'max:360'],
        ];
    }
}
```

- [ ] **Step 6: Create BillController**

```php
<?php
// app/Modules/Finance/Http/Controllers/BillController.php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\GenerateInstallmentsAction;
use App\Modules\Finance\Actions\UpdateBillAction;
use App\Modules\Finance\Http\Requests\StoreBillRequest;
use App\Modules\Finance\Http\Requests\UpdateBillRequest;
use App\Modules\Finance\Models\Bill;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Bill::class);

        $bills = Bill::withCount([
                'installments',
                'installments as paid_installments_count' => fn ($q) => $q->whereNotNull('paid_at'),
            ])
            ->when(request('bill_type'), fn ($q, $t) => $q->where('bill_type', $t))
            ->when(request('supplier'), fn ($q, $s) => $q->where('supplier', 'ilike', "%{$s}%"))
            ->orderByDesc('due_date')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Finance/Bills/Index', [
            'bills'   => $bills,
            'filters' => request()->only('bill_type', 'supplier'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Bill::class);

        return Inertia::render('Finance/Bills/Form');
    }

    public function store(StoreBillRequest $request, GenerateInstallmentsAction $action): RedirectResponse
    {
        $this->authorize('create', Bill::class);

        $bill = $action->handle($request->validated());

        return redirect()->route('bills.show', $bill)->with('success', 'Conta criada com sucesso.');
    }

    public function show(Bill $bill): Response
    {
        $this->authorize('view', $bill);

        $bill->load(['installments.payments']);

        return Inertia::render('Finance/Bills/Show', [
            'bill'    => $bill,
            'methods' => ['pix', 'transferencia', 'dinheiro', 'cheque', 'boleto'],
        ]);
    }

    public function edit(Bill $bill): Response
    {
        $this->authorize('update', $bill);

        return Inertia::render('Finance/Bills/Form', [
            'bill' => $bill,
        ]);
    }

    public function update(UpdateBillRequest $request, Bill $bill, UpdateBillAction $action): RedirectResponse
    {
        $this->authorize('update', $bill);

        $action->handle($bill, $request->validated());

        return redirect()->route('bills.show', $bill)->with('success', 'Conta atualizada com sucesso.');
    }

    public function destroy(Bill $bill): RedirectResponse
    {
        $this->authorize('delete', $bill);

        if ($bill->hasPayments()) {
            abort(403, 'Não é possível excluir uma conta com pagamentos registrados.');
        }

        $bill->delete();

        return redirect()->route('bills.index')->with('success', 'Conta removida com sucesso.');
    }
}
```

- [ ] **Step 7: Run tests — expect pass**

```bash
php artisan test tests/Feature/Finance/BillControllerTest.php
```

Expected: All passing.

- [ ] **Step 8: Run full test suite**

```bash
php artisan test
```

- [ ] **Step 9: Commit**

```bash
git add app/Modules/Finance/Http/Requests/StoreBillRequest.php \
        app/Modules/Finance/Http/Requests/UpdateBillRequest.php \
        app/Modules/Finance/Http/Controllers/BillController.php \
        tests/Feature/Finance/BillControllerTest.php \
        routes/web.php
git commit -m "feat(finance): add Bill CRUD controller with policy gates"
```

---

## Task 7: RecordBillPaymentAction (TDD)

**Files:**
- Create: `tests/Feature/Finance/RecordBillPaymentTest.php`
- Create: `app/Modules/Finance/Actions/RecordBillPaymentAction.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/Finance/RecordBillPaymentTest.php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Actions\RecordBillPaymentAction;
use App\Modules\Finance\Models\BillInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class RecordBillPaymentTest extends TenantTestCase
{
    use RefreshDatabase;

    private RecordBillPaymentAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(RecordBillPaymentAction::class);
    }

    public function test_partial_payment_sets_partially_paid_status(): void
    {
        $user        = $this->makeUserWithRole('Financial');
        $installment = BillInstallment::factory()->create([
            'company_id'  => $user->company_id,
            'amount'      => '1000.00',
            'paid_amount' => null,
        ]);

        $this->actingAsTenant($user);
        $this->action->handle($installment, [
            'amount'  => '400.00',
            'method'  => 'pix',
            'paid_at' => now()->toDateTimeString(),
            'notes'   => null,
        ]);

        $installment->refresh();
        $this->assertEquals('400.00', $installment->paid_amount);
        $this->assertEquals('partially_paid', $installment->status);
        $this->assertNull($installment->paid_at);
    }

    public function test_full_payment_sets_paid_status_and_paid_at(): void
    {
        $user        = $this->makeUserWithRole('Financial');
        $installment = BillInstallment::factory()->create([
            'company_id'  => $user->company_id,
            'amount'      => '500.00',
            'paid_amount' => null,
        ]);

        $this->actingAsTenant($user);
        $this->action->handle($installment, [
            'amount'  => '500.00',
            'method'  => 'transferencia',
            'paid_at' => now()->toDateTimeString(),
            'notes'   => null,
        ]);

        $installment->refresh();
        $this->assertEquals('500.00', $installment->paid_amount);
        $this->assertEquals('paid', $installment->status);
        $this->assertNotNull($installment->paid_at);
    }

    public function test_payment_record_is_persisted_with_bill_installment_type(): void
    {
        $user        = $this->makeUserWithRole('Financial');
        $installment = BillInstallment::factory()->create([
            'company_id'  => $user->company_id,
            'amount'      => '300.00',
        ]);

        $this->actingAsTenant($user);
        $this->action->handle($installment, [
            'amount'  => '300.00',
            'method'  => 'boleto',
            'paid_at' => '2026-04-24 10:00:00',
            'notes'   => 'Comprovante #456',
        ]);

        $this->assertDatabaseHas('payments', [
            'company_id'   => $user->company_id,
            'payable_type' => 'bill_installment',
            'payable_id'   => $installment->id,
            'amount'       => '300.00',
            'method'       => 'boleto',
        ]);
    }

    public function test_cumulative_partial_payments_eventually_mark_paid(): void
    {
        $user        = $this->makeUserWithRole('Financial');
        $installment = BillInstallment::factory()->create([
            'company_id'  => $user->company_id,
            'amount'      => '1000.00',
            'paid_amount' => null,
        ]);

        $this->actingAsTenant($user);
        $this->action->handle($installment, ['amount' => '600.00', 'method' => 'pix', 'paid_at' => now()->toDateTimeString(), 'notes' => null]);
        $this->action->handle($installment->fresh(), ['amount' => '400.00', 'method' => 'pix', 'paid_at' => now()->toDateTimeString(), 'notes' => null]);

        $installment->refresh();
        $this->assertEquals('paid', $installment->status);
        $this->assertNotNull($installment->paid_at);
    }
}
```

- [ ] **Step 2: Run tests — expect failure**

```bash
php artisan test tests/Feature/Finance/RecordBillPaymentTest.php
```

- [ ] **Step 3: Implement RecordBillPaymentAction**

```php
<?php
// app/Modules/Finance/Actions/RecordBillPaymentAction.php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\BillInstallment;
use App\Modules\Finance\Models\Payment;

class RecordBillPaymentAction
{
    /** @param array<string, mixed> $data */
    public function handle(BillInstallment $installment, array $data): Payment
    {
        $payment = Payment::create([
            'company_id'   => $installment->company_id,
            'payable_type' => 'bill_installment',
            'payable_id'   => $installment->id,
            'amount'       => $data['amount'],
            'paid_at'      => $data['paid_at'],
            'method'       => $data['method'],
            'notes'        => $data['notes'] ?? null,
        ]);

        $newPaidAmount = bcadd(
            (string) ($installment->paid_amount ?? 0),
            (string) $data['amount'],
            2
        );

        $installment->paid_amount = $newPaidAmount;
        $isPaid = $installment->isFullyPaid();

        $installment->update([
            'paid_amount' => $newPaidAmount,
            'paid_at'     => $isPaid ? now() : null,
        ]);

        return $payment;
    }
}
```

- [ ] **Step 4: Run tests — expect pass**

```bash
php artisan test tests/Feature/Finance/RecordBillPaymentTest.php
```

- [ ] **Step 5: Run full test suite**

```bash
php artisan test
```

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Finance/Actions/RecordBillPaymentAction.php \
        tests/Feature/Finance/RecordBillPaymentTest.php
git commit -m "feat(finance): implement RecordBillPaymentAction"
```

---

## Task 8: BillPaymentController (TDD)

**Files:**
- Create: `app/Modules/Finance/Http/Requests/StoreBillPaymentRequest.php`
- Create: `app/Modules/Finance/Http/Controllers/BillPaymentController.php`

- [ ] **Step 1: Create StoreBillPaymentRequest**

```php
<?php
// app/Modules/Finance/Http/Requests/StoreBillPaymentRequest.php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'amount'  => ['required', 'numeric', 'min:0.01'],
            'method'  => ['required', Rule::in(['pix', 'transferencia', 'dinheiro', 'cheque', 'boleto'])],
            'paid_at' => ['required', 'date'],
            'notes'   => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 2: Create BillPaymentController**

```php
<?php
// app/Modules/Finance/Http/Controllers/BillPaymentController.php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\RecordBillPaymentAction;
use App\Modules\Finance\Http\Requests\StoreBillPaymentRequest;
use App\Modules\Finance\Models\BillInstallment;
use Illuminate\Http\RedirectResponse;

class BillPaymentController extends Controller
{
    public function store(
        StoreBillPaymentRequest $request,
        BillInstallment $installment,
        RecordBillPaymentAction $action
    ): RedirectResponse {
        $this->authorize('recordPayment', $installment);

        $action->handle($installment, $request->validated());

        return redirect()->route('bills.show', $installment->bill_id)
            ->with('success', 'Pagamento registrado.');
    }
}
```

- [ ] **Step 3: Add feature tests for the controller**

Add to `tests/Feature/Finance/BillControllerTest.php`:

```php
public function test_financial_can_record_installment_payment(): void
{
    $user        = $this->makeUserWithRole('Financial');
    $installment = BillInstallment::factory()->create([
        'company_id'  => $user->company_id,
        'amount'      => '500.00',
        'paid_amount' => null,
    ]);

    $response = $this->actingAsTenant($user)->post("/bill-installments/{$installment->id}/payments", [
        'amount'  => '500.00',
        'method'  => 'pix',
        'paid_at' => '2026-04-24 10:00:00',
    ]);

    $response->assertRedirect();
    $installment->refresh();
    $this->assertEquals('paid', $installment->status);
}

public function test_operator_cannot_record_installment_payment(): void
{
    $user        = $this->makeUserWithRole('Operator');
    $installment = BillInstallment::factory()->create(['company_id' => $user->company_id]);

    $this->actingAsTenant($user)->post("/bill-installments/{$installment->id}/payments", [
        'amount'  => '100.00',
        'method'  => 'pix',
        'paid_at' => now()->toDateTimeString(),
    ])->assertForbidden();
}
```

- [ ] **Step 4: Run full test suite**

```bash
php artisan test
```

Expected: All passing.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Http/Requests/StoreBillPaymentRequest.php \
        app/Modules/Finance/Http/Controllers/BillPaymentController.php \
        tests/Feature/Finance/BillControllerTest.php
git commit -m "feat(finance): add BillPaymentController"
```

---

## Task 9: GenerateRecurringBillInstallmentsCommand (TDD)

Generates the next installment for each recurring bill when `last_due_date + cadence <= today` (generate-when-due, not lookahead).

**Files:**
- Create: `tests/Feature/Finance/GenerateRecurringInstallmentsTest.php`
- Create: `app/Console/Commands/Finance/GenerateRecurringBillInstallmentsCommand.php`
- Modify: `routes/console.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/Finance/GenerateRecurringInstallmentsTest.php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class GenerateRecurringInstallmentsTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_generates_next_installment_when_last_due_date_plus_cadence_is_today(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = Bill::factory()->recurring('monthly')->create([
            'company_id'     => $user->company_id,
            'due_date'       => now()->subMonth()->toDateString(),
            'recurrence_day' => now()->day,
        ]);
        BillInstallment::factory()->create([
            'company_id' => $user->company_id,
            'bill_id'    => $bill->id,
            'sequence'   => 1,
            'due_date'   => now()->subMonth()->toDateString(),
        ]);

        $this->artisan('finance:generate-recurring-installments')->assertExitCode(0);

        $this->assertEquals(2, BillInstallment::where('bill_id', $bill->id)->count());
    }

    public function test_does_not_generate_when_next_due_date_is_in_the_future(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = Bill::factory()->recurring('monthly')->create([
            'company_id'     => $user->company_id,
            'due_date'       => now()->addDays(5)->toDateString(),
            'recurrence_day' => now()->addDays(5)->day,
        ]);
        BillInstallment::factory()->create([
            'company_id' => $user->company_id,
            'bill_id'    => $bill->id,
            'sequence'   => 1,
            'due_date'   => now()->addDays(5)->toDateString(),
        ]);

        $this->artisan('finance:generate-recurring-installments')->assertExitCode(0);

        $this->assertEquals(1, BillInstallment::where('bill_id', $bill->id)->count());
    }

    public function test_idempotent_when_installment_for_date_already_exists(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = Bill::factory()->recurring('monthly')->create([
            'company_id'     => $user->company_id,
            'due_date'       => now()->subMonth()->toDateString(),
            'recurrence_day' => now()->day,
        ]);
        BillInstallment::factory()->create([
            'company_id' => $user->company_id,
            'bill_id'    => $bill->id,
            'sequence'   => 1,
            'due_date'   => now()->subMonth()->toDateString(),
        ]);

        $this->artisan('finance:generate-recurring-installments')->assertExitCode(0);
        $this->artisan('finance:generate-recurring-installments')->assertExitCode(0);

        $this->assertEquals(2, BillInstallment::where('bill_id', $bill->id)->count());
    }

    public function test_stops_generation_past_recurrence_end(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = Bill::factory()->recurring('monthly')->create([
            'company_id'     => $user->company_id,
            'due_date'       => now()->subMonth()->toDateString(),
            'recurrence_day' => now()->day,
            'recurrence_end' => now()->subDays(2)->toDateString(),
        ]);
        BillInstallment::factory()->create([
            'company_id' => $user->company_id,
            'bill_id'    => $bill->id,
            'sequence'   => 1,
            'due_date'   => now()->subMonth()->toDateString(),
        ]);

        $this->artisan('finance:generate-recurring-installments')->assertExitCode(0);

        $this->assertEquals(1, BillInstallment::where('bill_id', $bill->id)->count());
    }

    public function test_soft_deleted_bills_are_skipped(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = Bill::factory()->recurring('monthly')->create([
            'company_id'     => $user->company_id,
            'due_date'       => now()->subMonth()->toDateString(),
            'recurrence_day' => now()->day,
        ]);
        BillInstallment::factory()->create([
            'company_id' => $user->company_id,
            'bill_id'    => $bill->id,
            'sequence'   => 1,
            'due_date'   => now()->subMonth()->toDateString(),
        ]);
        $bill->delete();

        $this->artisan('finance:generate-recurring-installments')->assertExitCode(0);

        $this->assertEquals(1, BillInstallment::where('bill_id', $bill->id)->count());
    }
}
```

- [ ] **Step 2: Run tests — expect failure**

```bash
php artisan test tests/Feature/Finance/GenerateRecurringInstallmentsTest.php
```

- [ ] **Step 3: Implement GenerateRecurringBillInstallmentsCommand**

```php
<?php
// app/Console/Commands/Finance/GenerateRecurringBillInstallmentsCommand.php

namespace App\Console\Commands\Finance;

use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillInstallment;
use App\Modules\Finance\Services\BusinessDayCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateRecurringBillInstallmentsCommand extends Command
{
    protected $signature = 'finance:generate-recurring-installments';
    protected $description = 'Generate the next installment for recurring bills when their next due date has arrived';

    public function __construct(private BusinessDayCalculator $calculator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Bypass RLS: this command operates across all tenants
        DB::statement("SET LOCAL app.current_company_id = ''");

        $generated = 0;

        Bill::where('bill_type', 'recurring')
            ->cursor()
            ->each(function (Bill $bill) use (&$generated) {
                $last = $bill->installments()->orderByDesc('due_date')->first();

                if (! $last) {
                    return;
                }

                $nextDue = $this->calculator->nextDate(
                    Carbon::parse($last->due_date),
                    $bill->recurrence_cadence,
                    (int) $bill->recurrence_day,
                );

                if ($bill->recurrence_end && $nextDue->gt(Carbon::parse($bill->recurrence_end))) {
                    return;
                }

                if ($nextDue->isAfter(today())) {
                    return;
                }

                $created = $bill->installments()->firstOrCreate(
                    ['due_date' => $nextDue->toDateString()],
                    [
                        'company_id' => $bill->company_id,
                        'sequence'   => $last->sequence + 1,
                        'amount'     => $bill->total_amount,
                    ]
                );

                if ($created->wasRecentlyCreated) {
                    $generated++;
                }
            });

        $this->info("Generated {$generated} recurring installment(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Schedule command in routes/console.php**

Add to `routes/console.php`:

```php
use App\Console\Commands\Finance\GenerateRecurringBillInstallmentsCommand;

Schedule::command(GenerateRecurringBillInstallmentsCommand::class)->dailyAt('02:00');
```

- [ ] **Step 5: Run tests — expect pass**

```bash
php artisan test tests/Feature/Finance/GenerateRecurringInstallmentsTest.php
```

- [ ] **Step 6: Run full test suite**

```bash
php artisan test
```

Expected: All passing.

- [ ] **Step 7: Commit**

```bash
git add app/Console/Commands/Finance/GenerateRecurringBillInstallmentsCommand.php \
        tests/Feature/Finance/GenerateRecurringInstallmentsTest.php \
        routes/console.php
git commit -m "feat(finance): add GenerateRecurringBillInstallmentsCommand (generate-when-due)"
```

---

## Task 10: Frontend — Bills (Index + Form + Show)

**Files:**
- Create: `resources/js/Pages/Finance/Bills/Index.vue`
- Create: `resources/js/Pages/Finance/Bills/Form.vue`
- Create: `resources/js/Pages/Finance/Bills/Show.vue`
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`

- [ ] **Step 1: Create Bills/Index.vue**

Receives `bills` (paginated, each with `installments_count` and `paid_installments_count`), `filters`.

Table columns: Fornecedor, Tipo, Valor por Parcela, Vencimento, Progresso, Ações (Ver | Editar | Remover).

**Progress column** — shows `paid_installments_count / installments_count`:
- Installment bill: `"3/10"`
- Recurring with `recurrence_end`: `"3/∞"`
- Recurring without `recurrence_end`: `"3/∞"`

```javascript
progress(bill) {
    const paid = bill.paid_installments_count ?? 0
    const total = (bill.bill_type === 'recurring') ? '∞' : bill.installments_count
    return `${paid}/${total}`
}
```

Filter bar: `bill_type` select (`{ one_time: 'Única', recurring: 'Recorrente', installment: 'Parcelada' }`), `supplier` text.

Soft delete confirmation dialog with `useForm().delete()`.

- [ ] **Step 2: Create Bills/Form.vue**

Receives optional `bill` (null on create).

Always visible: `supplier`, `description`, `bill_type` (select), `total_amount`, `due_date`.

Show/hide field sets by `bill_type`:

```javascript
// computed:
showInstallmentFields() { return this.form.bill_type === 'installment' },
showRecurringFields() { return ['recurring', 'installment'].includes(this.form.bill_type) },
```

Recurring+installment fields: `recurrence_cadence` select (`{ weekly: 'Semanal', biweekly: 'Quinzenal', monthly: 'Mensal', yearly: 'Anual' }`), `recurrence_day` (only shown when cadence is `monthly` or `yearly`, label "Dia do mês (1-28)"), `recurrence_end`.

Installment-only: `installment_count`.

`total_amount` label changes: recurring shows "Valor por Parcela (R$)", others show "Valor Total (R$)".

- [ ] **Step 3: Create Bills/Show.vue**

Receives `bill` (with `installments` + computed `status`, `payments`), `methods`.

Two sections:
1. **Bill header**: supplier, type badge, per-installment value, outstanding balance (`bill.outstanding_balance` — pass from controller), description.
2. **Installments table**: columns Nº, Vencimento, Valor, Pago, Saldo, Status, Ações.

Status badge colors:
```javascript
statusClass(status) {
    return {
        open:           'bg-blue-100 text-blue-800',
        partially_paid: 'bg-yellow-100 text-yellow-800',
        paid:           'bg-green-100 text-green-800',
        overdue:        'bg-red-100 text-red-800',
    }[status] ?? 'bg-gray-100 text-gray-800'
}
```

Each unpaid/partially-paid installment row has a "Registrar Pagamento" button that reveals an inline form (same pattern as `Receivables/Show.vue`) submitting to `route('bill-installments.payments.store', installment.id)`.

Pass `outstandingBalance` from `BillController::show()`:

```php
// In BillController::show():
return Inertia::render('Finance/Bills/Show', [
    'bill'               => $bill,
    'outstanding_balance' => $bill->outstandingBalance(),
    'methods'            => ['pix', 'transferencia', 'dinheiro', 'cheque', 'boleto'],
]);
```

- [ ] **Step 4: Add "Contas a Pagar" nav link to AuthenticatedLayout.vue**

```html
<NavLink :href="route('bills.index')" :active="route().current('bills.*')">
    Contas a Pagar
</NavLink>
```

- [ ] **Step 5: Build and verify**

```bash
npm run build 2>&1 | tail -5
php artisan test
```

Expected: `✓ built`, all tests passing.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Finance/Bills/ \
        resources/js/Layouts/AuthenticatedLayout.vue
git commit -m "feat(finance): add Bills Index, Form, and Show pages"
```

---

## Self-Review Against Spec

| Spec requirement | Covered in |
|---|---|
| Bills CRUD (one-time / installment / recurring) | Tasks 1, 5, 6, 10 |
| Installment bills: all N installments generated on store | Task 5 (`GenerateInstallmentsAction`) |
| Recurring bills: **1 installment on store** | Task 5 (`generateFirstRecurring`) |
| Recurring scheduler: generate-when-due (`last_due + cadence <= today`) | Task 9 |
| Recurring stops at `recurrence_end` | Task 9 |
| Business-day adjustment (weekends + BR holidays) | Task 4 (`BusinessDayCalculator`) |
| All four cadences: weekly, biweekly, monthly, yearly | Tasks 1 (enum), 5, 9 |
| `paid_amount` nullable (null = unpaid) | Tasks 1, 2 |
| `status` as computed PHP property (not stored) | Task 2 (`getStatusAttribute`) |
| Installment payment recording via `payments` table | Tasks 7, 8 |
| Progress display `N/total` and `N/∞` for recurring | Tasks 6, 10 |
| Outstanding balance on show page | Tasks 2 (`outstandingBalance()`), 6, 10 |
| Tenant isolation (RLS) | Task 1 |
| Role gates: Admin + Financial only; Operator blocked | Task 3 (`BillPolicy`) |
| Soft delete with payment block | Task 6 (`BillController::destroy`) |
| Idempotent recurring generation | Task 9 (`firstOrCreate` on `due_date`) |
