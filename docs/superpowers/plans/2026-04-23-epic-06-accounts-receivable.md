# Epic 06 — Accounts Receivable Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Finance: Accounts Receivable module — auto-create receivables when a freight enters AwaitingPayment, allow Financial role to record payments, auto-complete freight on full payment, and detect overdue receivables daily.

**Architecture:** Event-driven receivable creation (listener on `FreightEnteredAwaitingPayment`). `RecordPaymentAction` handles payment recording, receivable status updates, and freight auto-completion. Overdue detection via a scheduled Artisan command. Frontend: Inertia + Vue 3 Options API matching existing module patterns.

**Tech Stack:** Laravel 11, Inertia.js, Vue 3 Options API, PostgreSQL, spatie/laravel-model-states, spatie/laravel-permission (roles: Admin, Operator, Financial), TailwindCSS.

---

## Architectural Decisions

| Decision | Choice | Reason |
|---|---|---|
| Payment polymorphism | Full `payable_type enum + payable_id` now | MVP plan specifies this; Epic 7 will need it |
| Receivable due_date default | today + 30 days | Gives Financial staff a usable starting point |
| Freight auto-complete | `RecordPaymentAction` directly calls `transitionTo(Completed::class)` | System-initiated; no UI guard needed; sets `completed_at` |
| Role access | Admin + Financial only for receivables/payments | MVP plan: Operators have no Finance write access |
| Overdue detection | Artisan command scheduled daily | Testable; simple; no queue needed for MVP |
| Payment method | Enum: pix, transferencia, dinheiro, cheque, boleto | Standard Brazilian payment methods |

---

## File Map

### New files
| Path | Responsibility |
|---|---|
| `database/migrations/2026_04_23_000001_create_receivables_table.php` | Receivables schema + indexes |
| `database/migrations/2026_04_23_000002_create_payments_table.php` | Payments schema |
| `database/migrations/rls/2026_04_23_000003_enable_rls_on_finance_tables.php` | RLS policies on receivables + payments |
| `database/factories/Finance/ReceivableFactory.php` | Test factory |
| `database/factories/Finance/PaymentFactory.php` | Test factory |
| `app/Modules/Finance/Models/Receivable.php` | BelongsToCompany, relations to Client/Freight/Payment |
| `app/Modules/Finance/Models/Payment.php` | BelongsToCompany, morphTo payable |
| `app/Modules/Finance/Policies/ReceivablePolicy.php` | Admin+Financial can view/create; no Operator access |
| `app/Modules/Finance/Policies/PaymentPolicy.php` | Admin+Financial can create |
| `app/Modules/Finance/Listeners/CreateReceivableForFreight.php` | Creates receivable on `FreightEnteredAwaitingPayment` |
| `app/Modules/Finance/Actions/RecordPaymentAction.php` | Records payment, updates receivable status, auto-completes freight |
| `app/Modules/Finance/Http/Controllers/ReceivableController.php` | index (with filters), show |
| `app/Modules/Finance/Http/Controllers/PaymentController.php` | store |
| `app/Modules/Finance/Http/Requests/StorePaymentRequest.php` | Validates payment input |
| `app/Console/Commands/DetectOverdueReceivablesCommand.php` | Bulk-updates open→overdue past due_date |
| `resources/js/Pages/Finance/Receivables/Index.vue` | Receivables list with filters |
| `resources/js/Pages/Finance/Receivables/Show.vue` | Receivable detail + payment history + pay form |
| `tests/Feature/Finance/CreateReceivableListenerTest.php` | Tests auto-creation on event |
| `tests/Feature/Finance/ReceivableControllerTest.php` | Tests index, show, tenant isolation, role gates |
| `tests/Feature/Finance/RecordPaymentActionTest.php` | Tests payment logic and freight auto-complete |
| `tests/Feature/Finance/PaymentControllerTest.php` | Tests store, role gates |
| `tests/Feature/Finance/DetectOverdueReceivablesTest.php` | Tests command marks overdue |

### Modified files
| Path | Change |
|---|---|
| `routes/web.php` | Add receivables resource + payments.store route |
| `routes/console.php` | Schedule `receivables:detect-overdue` daily |
| `app/Providers/AppServiceProvider.php` | Register policies + event listener |

---

## Task 1: Migrations

**Files:**
- Create: `database/migrations/2026_04_23_000001_create_receivables_table.php`
- Create: `database/migrations/2026_04_23_000002_create_payments_table.php`
- Create: `database/migrations/rls/2026_04_23_000003_enable_rls_on_finance_tables.php`

- [ ] **Step 1: Create receivables migration**

```php
<?php
// database/migrations/2026_04_23_000001_create_receivables_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('freight_id')->nullable()->constrained('freights')->nullOnDelete();
            $table->decimal('amount_due', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->date('due_date');
            $table->enum('status', ['open', 'partially_paid', 'paid', 'overdue'])->default('open');
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'client_id']);
            $table->index(['company_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivables');
    }
};
```

- [ ] **Step 2: Create payments migration**

```php
<?php
// database/migrations/2026_04_23_000002_create_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->decimal('amount', 12, 2);
            $table->timestamp('paid_at');
            $table->enum('method', ['pix', 'transferencia', 'dinheiro', 'cheque', 'boleto']);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
```

- [ ] **Step 3: Create RLS migration**

```php
<?php
// database/migrations/rls/2026_04_23_000003_enable_rls_on_finance_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE receivables ENABLE ROW LEVEL SECURITY;
            ALTER TABLE receivables FORCE ROW LEVEL SECURITY;

            CREATE POLICY receivables_company_isolation ON receivables
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

            ALTER TABLE payments ENABLE ROW LEVEL SECURITY;
            ALTER TABLE payments FORCE ROW LEVEL SECURITY;

            CREATE POLICY payments_company_isolation ON payments
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
            DROP POLICY IF EXISTS receivables_company_isolation ON receivables;
            DROP POLICY IF EXISTS payments_company_isolation ON payments;
            ALTER TABLE receivables DISABLE ROW LEVEL SECURITY;
            ALTER TABLE payments DISABLE ROW LEVEL SECURITY;
        SQL);
    }
};
```

- [ ] **Step 4: Run migrations to verify they apply cleanly**

```bash
cd /var/www/html/FleetisV2/.worktrees/epic/06-accounts-receivable
php artisan migrate
```

Expected: No errors. Tables `receivables` and `payments` created.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_23_000001_create_receivables_table.php \
        database/migrations/2026_04_23_000002_create_payments_table.php \
        database/migrations/rls/2026_04_23_000003_enable_rls_on_finance_tables.php
git commit -m "feat(finance): add receivables and payments migrations with RLS"
```

---

## Task 2: Models and Factories

**Files:**
- Create: `app/Modules/Finance/Models/Receivable.php`
- Create: `app/Modules/Finance/Models/Payment.php`
- Create: `database/factories/Finance/ReceivableFactory.php`
- Create: `database/factories/Finance/PaymentFactory.php`

- [ ] **Step 1: Create Receivable model**

```php
<?php
// app/Modules/Finance/Models/Receivable.php

namespace App\Modules\Finance\Models;

use App\Modules\Commercial\Models\Client;
use App\Modules\Operations\Models\Freight;
use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Finance\ReceivableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Receivable extends Model
{
    /** @use HasFactory<ReceivableFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'client_id', 'freight_id', 'amount_due', 'amount_paid', 'due_date', 'status',
    ];

    protected $casts = [
        'amount_due'  => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'due_date'    => 'date',
    ];

    protected static function newFactory(): ReceivableFactory
    {
        return ReceivableFactory::new();
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Freight, $this> */
    public function freight(): BelongsTo
    {
        return $this->belongsTo(Freight::class);
    }

    /** @return MorphMany<Payment, $this> */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function isFullyPaid(): bool
    {
        return (float) $this->amount_paid >= (float) $this->amount_due;
    }

    public function resolveStatus(): string
    {
        if ($this->isFullyPaid()) {
            return 'paid';
        }

        if ((float) $this->amount_paid > 0) {
            return 'partially_paid';
        }

        return 'open';
    }
}
```

- [ ] **Step 2: Create Payment model**

```php
<?php
// app/Modules/Finance/Models/Payment.php

namespace App\Modules\Finance\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Finance\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'payable_type', 'payable_id', 'amount', 'paid_at', 'method', 'notes',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    /** @return MorphTo<Model, $this> */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

- [ ] **Step 3: Register morph map in AppServiceProvider boot()**

Add to `app/Providers/AppServiceProvider.php` inside `boot()`:

```php
use Illuminate\Database\Eloquent\Relations\Relation;

// in boot():
Relation::morphMap([
    'receivable' => \App\Modules\Finance\Models\Receivable::class,
]);
```

- [ ] **Step 4: Create ReceivableFactory**

```php
<?php
// database/factories/Finance/ReceivableFactory.php

namespace Database\Factories\Finance;

use App\Modules\Commercial\Models\Client;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Receivable> */
class ReceivableFactory extends Factory
{
    protected $model = Receivable::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'company_id' => $company->id,
            'client_id'  => Client::factory()->create(['company_id' => $company->id])->id,
            'freight_id' => null,
            'amount_due' => $this->faker->randomFloat(2, 500, 10000),
            'amount_paid' => 0,
            'due_date'   => now()->addDays(30)->toDateString(),
            'status'     => 'open',
        ];
    }

    public function overdue(): static
    {
        return $this->state(['status' => 'open', 'due_date' => now()->subDay()->toDateString()]);
    }

    public function partiallyPaid(float $paid): static
    {
        return $this->state(['amount_paid' => $paid, 'status' => 'partially_paid']);
    }
}
```

- [ ] **Step 5: Create PaymentFactory**

```php
<?php
// database/factories/Finance/PaymentFactory.php

namespace Database\Factories\Finance;

use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $company = Company::factory()->create();
        $receivable = Receivable::factory()->create(['company_id' => $company->id]);

        return [
            'company_id'   => $company->id,
            'payable_type' => 'receivable',
            'payable_id'   => $receivable->id,
            'amount'       => $this->faker->randomFloat(2, 100, 5000),
            'paid_at'      => now(),
            'method'       => 'pix',
            'notes'        => null,
        ];
    }
}
```

- [ ] **Step 6: Run tests to confirm baseline still passes**

```bash
php artisan test
```

Expected: 127 passed, 0 failed.

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Finance/Models/Receivable.php \
        app/Modules/Finance/Models/Payment.php \
        database/factories/Finance/ReceivableFactory.php \
        database/factories/Finance/PaymentFactory.php \
        app/Providers/AppServiceProvider.php
git commit -m "feat(finance): add Receivable and Payment models with factories"
```

---

## Task 3: Policies + AppServiceProvider Registration

**Files:**
- Create: `app/Modules/Finance/Policies/ReceivablePolicy.php`
- Create: `app/Modules/Finance/Policies/PaymentPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Create ReceivablePolicy**

```php
<?php
// app/Modules/Finance/Policies/ReceivablePolicy.php

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Tenancy\Policies\TenantPolicy;

class ReceivablePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function view(User $user, Receivable $receivable): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $this->belongsToTenant($user, $receivable);
    }
}
```

- [ ] **Step 2: Create PaymentPolicy**

```php
<?php
// app/Modules/Finance/Policies/PaymentPolicy.php

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Tenancy\Policies\TenantPolicy;

class PaymentPolicy extends TenantPolicy
{
    public function create(User $user, Receivable $receivable): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $this->belongsToTenant($user, $receivable);
    }
}
```

- [ ] **Step 3: Register policies and event listener in AppServiceProvider**

Add these imports and registrations to `app/Providers/AppServiceProvider.php`:

```php
// New imports to add at top:
use App\Modules\Finance\Listeners\CreateReceivableForFreight;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Finance\Policies\PaymentPolicy;
use App\Modules\Finance\Policies\ReceivablePolicy;
use App\Modules\Operations\Events\FreightEnteredAwaitingPayment;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;

// In boot(), add after existing Gate::policy() calls:
Gate::policy(Receivable::class, ReceivablePolicy::class);
Gate::policy(Payment::class, PaymentPolicy::class);

Event::listen(FreightEnteredAwaitingPayment::class, CreateReceivableForFreight::class);

Relation::morphMap([
    'receivable' => Receivable::class,
]);
```

Note: Remove the `Relation::morphMap` added in Task 2 Step 3 and consolidate it here.

- [ ] **Step 4: Run tests to confirm baseline still passes**

```bash
php artisan test
```

Expected: 127 passed, 0 failed.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Policies/ReceivablePolicy.php \
        app/Modules/Finance/Policies/PaymentPolicy.php \
        app/Providers/AppServiceProvider.php
git commit -m "feat(finance): add ReceivablePolicy, PaymentPolicy, and register event listener"
```

---

## Task 4: CreateReceivableForFreight Listener (TDD)

**Files:**
- Create: `tests/Feature/Finance/CreateReceivableListenerTest.php`
- Create: `app/Modules/Finance/Listeners/CreateReceivableForFreight.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Finance/CreateReceivableListenerTest.php

namespace Tests\Feature\Finance;

use App\Modules\Commercial\Models\Client;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\Events\FreightEnteredAwaitingPayment;
use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class CreateReceivableListenerTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_receivable_is_created_when_freight_enters_awaiting_payment(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $freight = Freight::factory()->awaitingPayment()->create([
            'company_id'    => $user->company_id,
            'freight_value' => '1500.00',
        ]);

        FreightEnteredAwaitingPayment::dispatch($freight);

        $this->assertDatabaseHas('receivables', [
            'company_id'  => $user->company_id,
            'client_id'   => $freight->client_id,
            'freight_id'  => $freight->id,
            'amount_due'  => '1500.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);
    }

    public function test_receivable_due_date_is_30_days_from_today(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $freight = Freight::factory()->awaitingPayment()->create([
            'company_id'    => $user->company_id,
            'freight_value' => '800.00',
        ]);

        FreightEnteredAwaitingPayment::dispatch($freight);

        $receivable = Receivable::where('freight_id', $freight->id)->first();
        $this->assertEquals(now()->addDays(30)->toDateString(), $receivable->due_date->toDateString());
    }

    public function test_no_receivable_created_when_freight_value_is_null(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $freight = Freight::factory()->awaitingPayment()->create([
            'company_id'    => $user->company_id,
            'freight_value' => null,
        ]);

        FreightEnteredAwaitingPayment::dispatch($freight);

        $this->assertDatabaseMissing('receivables', ['freight_id' => $freight->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Finance/CreateReceivableListenerTest.php
```

Expected: FAIL — `CreateReceivableForFreight` class not found.

- [ ] **Step 3: Implement the listener**

```php
<?php
// app/Modules/Finance/Listeners/CreateReceivableForFreight.php

namespace App\Modules\Finance\Listeners;

use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\Events\FreightEnteredAwaitingPayment;

class CreateReceivableForFreight
{
    public function handle(FreightEnteredAwaitingPayment $event): void
    {
        $freight = $event->freight;

        if ($freight->freight_value === null) {
            return;
        }

        Receivable::create([
            'company_id' => $freight->company_id,
            'client_id'  => $freight->client_id,
            'freight_id' => $freight->id,
            'amount_due' => $freight->freight_value,
            'amount_paid' => 0,
            'due_date'   => now()->addDays(30)->toDateString(),
            'status'     => 'open',
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test tests/Feature/Finance/CreateReceivableListenerTest.php
```

Expected: 3 passed.

- [ ] **Step 5: Run full test suite**

```bash
php artisan test
```

Expected: All passing.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Finance/Listeners/CreateReceivableForFreight.php \
        tests/Feature/Finance/CreateReceivableListenerTest.php
git commit -m "feat(finance): implement CreateReceivableForFreight listener"
```

---

## Task 5: RecordPaymentAction (TDD)

**Files:**
- Create: `tests/Feature/Finance/RecordPaymentActionTest.php`
- Create: `app/Modules/Finance/Actions/RecordPaymentAction.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Finance/RecordPaymentActionTest.php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Actions\RecordPaymentAction;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\Models\Freight;
use App\Modules\Operations\States\Completed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class RecordPaymentActionTest extends TenantTestCase
{
    use RefreshDatabase;

    private RecordPaymentAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(RecordPaymentAction::class);
    }

    public function test_partial_payment_sets_partially_paid_status(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id' => $user->company_id,
            'amount_due'  => '1000.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);

        $this->actingAsTenant($user);
        $this->action->handle($receivable, [
            'amount' => '400.00',
            'method' => 'pix',
            'paid_at' => now()->toDateTimeString(),
            'notes'  => null,
        ]);

        $receivable->refresh();
        $this->assertEquals('400.00', $receivable->amount_paid);
        $this->assertEquals('partially_paid', $receivable->status);
    }

    public function test_full_payment_sets_paid_status(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id' => $user->company_id,
            'amount_due'  => '1000.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);

        $this->actingAsTenant($user);
        $this->action->handle($receivable, [
            'amount' => '1000.00',
            'method' => 'transferencia',
            'paid_at' => now()->toDateTimeString(),
            'notes'  => null,
        ]);

        $receivable->refresh();
        $this->assertEquals('1000.00', $receivable->amount_paid);
        $this->assertEquals('paid', $receivable->status);
    }

    public function test_full_payment_transitions_linked_freight_to_completed(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $freight = Freight::factory()->awaitingPayment()->create([
            'company_id'    => $user->company_id,
            'freight_value' => '2000.00',
        ]);
        $receivable = Receivable::factory()->create([
            'company_id' => $user->company_id,
            'freight_id'  => $freight->id,
            'amount_due'  => '2000.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);

        $this->actingAsTenant($user);
        $this->action->handle($receivable, [
            'amount' => '2000.00',
            'method' => 'pix',
            'paid_at' => now()->toDateTimeString(),
            'notes'  => null,
        ]);

        $freight->refresh();
        $this->assertInstanceOf(Completed::class, $freight->status);
        $this->assertNotNull($freight->completed_at);
    }

    public function test_partial_payment_does_not_complete_freight(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $freight = Freight::factory()->awaitingPayment()->create([
            'company_id'    => $user->company_id,
            'freight_value' => '2000.00',
        ]);
        $receivable = Receivable::factory()->create([
            'company_id' => $user->company_id,
            'freight_id'  => $freight->id,
            'amount_due'  => '2000.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);

        $this->actingAsTenant($user);
        $this->action->handle($receivable, [
            'amount' => '500.00',
            'method' => 'pix',
            'paid_at' => now()->toDateTimeString(),
            'notes'  => null,
        ]);

        $freight->refresh();
        $this->assertEquals('awaiting_payment', (string) $freight->status);
    }

    public function test_payment_record_is_persisted(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id' => $user->company_id,
            'amount_due'  => '500.00',
            'amount_paid' => '0.00',
        ]);

        $this->actingAsTenant($user);
        $this->action->handle($receivable, [
            'amount' => '500.00',
            'method' => 'boleto',
            'paid_at' => '2026-04-23 10:00:00',
            'notes'  => 'Comprovante #123',
        ]);

        $this->assertDatabaseHas('payments', [
            'company_id'   => $user->company_id,
            'payable_type' => 'receivable',
            'payable_id'   => $receivable->id,
            'amount'       => '500.00',
            'method'       => 'boleto',
            'notes'        => 'Comprovante #123',
        ]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Finance/RecordPaymentActionTest.php
```

Expected: FAIL — `RecordPaymentAction` class not found.

- [ ] **Step 3: Implement RecordPaymentAction**

```php
<?php
// app/Modules/Finance/Actions/RecordPaymentAction.php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\States\Completed;

class RecordPaymentAction
{
    /** @param array<string, mixed> $data */
    public function handle(Receivable $receivable, array $data): Payment
    {
        $payment = $receivable->payments()->create([
            'company_id'   => $receivable->company_id,
            'payable_type' => 'receivable',
            'payable_id'   => $receivable->id,
            'amount'       => $data['amount'],
            'paid_at'      => $data['paid_at'],
            'method'       => $data['method'],
            'notes'        => $data['notes'] ?? null,
        ]);

        $newAmountPaid = bcadd((string) $receivable->amount_paid, (string) $data['amount'], 2);
        $receivable->update([
            'amount_paid' => $newAmountPaid,
            'status'      => $receivable->fresh()->fill(['amount_paid' => $newAmountPaid])->resolveStatus(),
        ]);

        $receivable->refresh();

        if ($receivable->isFullyPaid() && $receivable->freight_id) {
            $freight = $receivable->freight()->withoutGlobalScopes()->first();
            if ($freight && (string) $freight->status === 'awaiting_payment') {
                $freight->status->transitionTo(Completed::class);
                $freight->update(['completed_at' => now()]);
            }
        }

        return $payment;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Finance/RecordPaymentActionTest.php
```

Expected: 5 passed.

- [ ] **Step 5: Run full test suite**

```bash
php artisan test
```

Expected: All passing.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Finance/Actions/RecordPaymentAction.php \
        tests/Feature/Finance/RecordPaymentActionTest.php
git commit -m "feat(finance): implement RecordPaymentAction with freight auto-complete"
```

---

## Task 6: ReceivableController + Routes (TDD)

**Files:**
- Create: `tests/Feature/Finance/ReceivableControllerTest.php`
- Create: `app/Modules/Finance/Http/Controllers/ReceivableController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Finance/ReceivableControllerTest.php

namespace Tests\Feature\Finance;

use App\Modules\Commercial\Models\Client;
use App\Modules\Finance\Models\Receivable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class ReceivableControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_financial_can_list_receivables(): void
    {
        $user = $this->makeUserWithRole('Financial');
        Receivable::factory()->count(3)->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->get('/receivables');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Finance/Receivables/Index')
            ->has('receivables.data', 3)
        );
    }

    public function test_operator_cannot_access_receivables(): void
    {
        $user = $this->makeUserWithRole('Operator');

        $response = $this->actingAsTenant($user)->get('/receivables');

        $response->assertForbidden();
    }

    public function test_index_does_not_leak_other_company_receivables(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        Receivable::factory()->create(['company_id' => $userA->company_id]);
        Receivable::factory()->create(); // other company

        $response = $this->actingAsTenant($userA)->get('/receivables');

        $response->assertInertia(fn ($page) => $page->has('receivables.data', 1));
    }

    public function test_index_filters_by_status(): void
    {
        $user = $this->makeUserWithRole('Financial');
        Receivable::factory()->create(['company_id' => $user->company_id, 'status' => 'open']);
        Receivable::factory()->create(['company_id' => $user->company_id, 'status' => 'paid']);

        $response = $this->actingAsTenant($user)->get('/receivables?status=open');

        $response->assertInertia(fn ($page) => $page->has('receivables.data', 1));
    }

    public function test_index_filters_by_client(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $clientA = Client::factory()->create(['company_id' => $user->company_id]);
        $clientB = Client::factory()->create(['company_id' => $user->company_id]);
        Receivable::factory()->create(['company_id' => $user->company_id, 'client_id' => $clientA->id]);
        Receivable::factory()->create(['company_id' => $user->company_id, 'client_id' => $clientB->id]);

        $response = $this->actingAsTenant($user)->get("/receivables?client_id={$clientA->id}");

        $response->assertInertia(fn ($page) => $page->has('receivables.data', 1));
    }

    public function test_financial_can_view_receivable(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->get("/receivables/{$receivable->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Finance/Receivables/Show')
            ->has('receivable')
        );
    }

    public function test_financial_cannot_view_other_company_receivable(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $otherReceivable = Receivable::factory()->create(); // different company

        $response = $this->actingAsTenant($user)->get("/receivables/{$otherReceivable->id}");

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Finance/ReceivableControllerTest.php
```

Expected: FAIL — route not found.

- [ ] **Step 3: Add routes to web.php**

Add these lines inside the `Route::middleware(['auth', 'verified', 'tenant'])->group(function () {` block in `routes/web.php`:

```php
// Finance
use App\Modules\Finance\Http\Controllers\PaymentController;
use App\Modules\Finance\Http\Controllers\ReceivableController;

Route::resource('receivables', ReceivableController::class)->only(['index', 'show']);
Route::post('receivables/{receivable}/payments', [PaymentController::class, 'store'])->name('receivables.payments.store');
```

Also add the two `use` statements at the top of `routes/web.php` with the other imports.

- [ ] **Step 4: Implement ReceivableController**

```php
<?php
// app/Modules/Finance/Http/Controllers/ReceivableController.php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commercial\Models\Client;
use App\Modules\Finance\Models\Receivable;
use Inertia\Inertia;
use Inertia\Response;

class ReceivableController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Receivable::class);

        $receivables = Receivable::with(['client', 'freight'])
            ->when(request('status'), fn ($q, $s) => $q->where('status', $s))
            ->when(request('client_id'), fn ($q, $id) => $q->where('client_id', $id))
            ->when(request('due_date_from'), fn ($q, $d) => $q->whereDate('due_date', '>=', $d))
            ->when(request('due_date_to'), fn ($q, $d) => $q->whereDate('due_date', '<=', $d))
            ->orderByDesc('due_date')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Finance/Receivables/Index', [
            'receivables' => $receivables,
            'clients'     => Client::orderBy('name')->get(['id', 'name']),
            'filters'     => request()->only('status', 'client_id', 'due_date_from', 'due_date_to'),
        ]);
    }

    public function show(Receivable $receivable): Response
    {
        $this->authorize('view', $receivable);

        $receivable->load(['client', 'freight', 'payments']);

        return Inertia::render('Finance/Receivables/Show', [
            'receivable' => $receivable,
            'methods'    => ['pix', 'transferencia', 'dinheiro', 'cheque', 'boleto'],
        ]);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Finance/ReceivableControllerTest.php
```

Expected: 6 passed.

- [ ] **Step 6: Run full test suite**

```bash
php artisan test
```

Expected: All passing.

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Finance/Http/Controllers/ReceivableController.php \
        tests/Feature/Finance/ReceivableControllerTest.php \
        routes/web.php
git commit -m "feat(finance): add ReceivableController with index/show and routes"
```

---

## Task 7: PaymentController (TDD)

**Files:**
- Create: `tests/Feature/Finance/PaymentControllerTest.php`
- Create: `app/Modules/Finance/Http/Requests/StorePaymentRequest.php`
- Create: `app/Modules/Finance/Http/Controllers/PaymentController.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Finance/PaymentControllerTest.php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Models\Receivable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class PaymentControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_financial_can_record_payment(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id' => $user->company_id,
            'amount_due'  => '1000.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);

        $response = $this->actingAsTenant($user)->post("/receivables/{$receivable->id}/payments", [
            'amount'  => '1000.00',
            'method'  => 'pix',
            'paid_at' => '2026-04-23 10:00:00',
            'notes'   => null,
        ]);

        $response->assertRedirect("/receivables/{$receivable->id}");
        $this->assertDatabaseHas('receivables', [
            'id'          => $receivable->id,
            'amount_paid' => '1000.00',
            'status'      => 'paid',
        ]);
    }

    public function test_operator_cannot_record_payment(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $receivable = Receivable::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->post("/receivables/{$receivable->id}/payments", [
            'amount'  => '500.00',
            'method'  => 'pix',
            'paid_at' => now()->toDateTimeString(),
        ]);

        $response->assertForbidden();
    }

    public function test_financial_cannot_pay_other_company_receivable(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $otherReceivable = Receivable::factory()->create(); // different company

        $response = $this->actingAsTenant($user)->post("/receivables/{$otherReceivable->id}/payments", [
            'amount'  => '500.00',
            'method'  => 'pix',
            'paid_at' => now()->toDateTimeString(),
        ]);

        $response->assertForbidden();
    }

    public function test_payment_requires_valid_method(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->post("/receivables/{$receivable->id}/payments", [
            'amount'  => '500.00',
            'method'  => 'invalid_method',
            'paid_at' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors('method');
    }

    public function test_payment_amount_must_be_positive(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->post("/receivables/{$receivable->id}/payments", [
            'amount'  => '0',
            'method'  => 'pix',
            'paid_at' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors('amount');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Finance/PaymentControllerTest.php
```

Expected: FAIL — controller not found.

- [ ] **Step 3: Create StorePaymentRequest**

```php
<?php
// app/Modules/Finance/Http/Requests/StorePaymentRequest.php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
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

- [ ] **Step 4: Create PaymentController**

```php
<?php
// app/Modules/Finance/Http/Controllers/PaymentController.php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\RecordPaymentAction;
use App\Modules\Finance\Http\Requests\StorePaymentRequest;
use App\Modules\Finance\Models\Receivable;
use Illuminate\Http\RedirectResponse;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Receivable $receivable, RecordPaymentAction $action): RedirectResponse
    {
        $this->authorize('create', [Payment::class, $receivable]);

        $action->handle($receivable, $request->validated());

        return redirect()->route('receivables.show', $receivable)->with('success', 'Pagamento registrado.');
    }
}
```

Note: add `use App\Modules\Finance\Models\Payment;` import at the top of PaymentController.php.

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Finance/PaymentControllerTest.php
```

Expected: 5 passed.

- [ ] **Step 6: Run full test suite**

```bash
php artisan test
```

Expected: All passing.

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Finance/Http/Controllers/PaymentController.php \
        app/Modules/Finance/Http/Requests/StorePaymentRequest.php \
        tests/Feature/Finance/PaymentControllerTest.php
git commit -m "feat(finance): add PaymentController and StorePaymentRequest"
```

---

## Task 8: DetectOverdueReceivablesCommand (TDD)

**Files:**
- Create: `tests/Feature/Finance/DetectOverdueReceivablesTest.php`
- Create: `app/Console/Commands/DetectOverdueReceivablesCommand.php`
- Modify: `routes/console.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Finance/DetectOverdueReceivablesTest.php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Models\Receivable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class DetectOverdueReceivablesTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_open_receivables_past_due_date_become_overdue(): void
    {
        $user = $this->makeUserWithRole('Financial');
        Receivable::factory()->create([
            'company_id' => $user->company_id,
            'status'     => 'open',
            'due_date'   => now()->subDay()->toDateString(),
        ]);

        $this->artisan('receivables:detect-overdue')->assertExitCode(0);

        $this->assertDatabaseHas('receivables', [
            'company_id' => $user->company_id,
            'status'     => 'overdue',
        ]);
    }

    public function test_future_open_receivables_are_not_affected(): void
    {
        $user = $this->makeUserWithRole('Financial');
        Receivable::factory()->create([
            'company_id' => $user->company_id,
            'status'     => 'open',
            'due_date'   => now()->addDay()->toDateString(),
        ]);

        $this->artisan('receivables:detect-overdue')->assertExitCode(0);

        $this->assertDatabaseHas('receivables', [
            'company_id' => $user->company_id,
            'status'     => 'open',
        ]);
    }

    public function test_paid_receivables_are_not_affected(): void
    {
        $user = $this->makeUserWithRole('Financial');
        Receivable::factory()->create([
            'company_id' => $user->company_id,
            'status'     => 'paid',
            'due_date'   => now()->subDay()->toDateString(),
        ]);

        $this->artisan('receivables:detect-overdue')->assertExitCode(0);

        $this->assertDatabaseHas('receivables', [
            'company_id' => $user->company_id,
            'status'     => 'paid',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Finance/DetectOverdueReceivablesTest.php
```

Expected: FAIL — command not found.

- [ ] **Step 3: Implement the command**

```php
<?php
// app/Console/Commands/DetectOverdueReceivablesCommand.php

namespace App\Console\Commands;

use App\Modules\Finance\Models\Receivable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DetectOverdueReceivablesCommand extends Command
{
    protected $signature = 'receivables:detect-overdue';
    protected $description = 'Mark open receivables past their due date as overdue';

    public function handle(): int
    {
        $count = DB::table('receivables')
            ->whereIn('status', ['open', 'partially_paid'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue', 'updated_at' => now()]);

        $this->info("Marked {$count} receivable(s) as overdue.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Schedule the command in routes/console.php**

Replace the contents of `routes/console.php` with:

```php
<?php

use App\Console\Commands\DetectOverdueReceivablesCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(DetectOverdueReceivablesCommand::class)->dailyAt('01:00');
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Finance/DetectOverdueReceivablesTest.php
```

Expected: 3 passed.

- [ ] **Step 6: Run full test suite**

```bash
php artisan test
```

Expected: All passing.

- [ ] **Step 7: Commit**

```bash
git add app/Console/Commands/DetectOverdueReceivablesCommand.php \
        tests/Feature/Finance/DetectOverdueReceivablesTest.php \
        routes/console.php
git commit -m "feat(finance): add DetectOverdueReceivablesCommand scheduled daily"
```

---

## Task 9: Frontend — Receivables/Index.vue

**Files:**
- Create: `resources/js/Pages/Finance/Receivables/Index.vue`

- [ ] **Step 1: Create the Index page**

```vue
<!-- resources/js/Pages/Finance/Receivables/Index.vue -->
<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        receivables: Object,
        clients: Array,
        filters: Object,
    },

    data() {
        return {
            localFilters: {
                status: this.filters?.status ?? '',
                client_id: this.filters?.client_id ?? '',
                due_date_from: this.filters?.due_date_from ?? '',
                due_date_to: this.filters?.due_date_to ?? '',
            },
        }
    },

    methods: {
        search() {
            router.get(route('receivables.index'), this.localFilters, { preserveState: true, replace: true })
        },
        statusClass(status) {
            return {
                open: 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
                partially_paid: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
                paid: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
                overdue: 'bg-red-50 text-red-700 ring-1 ring-red-200',
            }[status] ?? 'bg-gray-100 text-gray-500'
        },
        statusLabel(status) {
            return {
                open: 'Em aberto',
                partially_paid: 'Parcial',
                paid: 'Pago',
                overdue: 'Vencido',
            }[status] ?? status
        },
        formatCurrency(value) {
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)
        },
    },
}
</script>

<template>
    <Head title="Contas a Receber" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Contas a Receber</h1>
        </template>

        <!-- Filters -->
        <div class="mb-5 flex flex-wrap gap-3">
            <select
                v-model="localFilters.status"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                @change="search"
            >
                <option value="">Todos os status</option>
                <option value="open">Em aberto</option>
                <option value="partially_paid">Parcialmente pago</option>
                <option value="paid">Pago</option>
                <option value="overdue">Vencido</option>
            </select>

            <select
                v-model="localFilters.client_id"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                @change="search"
            >
                <option value="">Todos os clientes</option>
                <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}</option>
            </select>

            <input
                v-model="localFilters.due_date_from"
                type="date"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                placeholder="Vencimento de"
                @change="search"
            />
            <input
                v-model="localFilters.due_date_to"
                type="date"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                placeholder="Vencimento até"
                @change="search"
            />
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Frete</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Valor</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Pago</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Vencimento</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                        <th class="px-6 py-3.5" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-for="rec in receivables.data" :key="rec.id" class="hover:bg-gray-50 transition-colors">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ rec.client?.name }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                            <span v-if="rec.freight_id">#{{ rec.freight_id }}</span>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium text-gray-900">{{ formatCurrency(rec.amount_due) }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-600">{{ formatCurrency(rec.amount_paid) }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ rec.due_date }}</td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <span :class="statusClass(rec.status)" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                                {{ statusLabel(rec.status) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                            <Link :href="route('receivables.show', rec.id)" class="font-medium text-indigo-600 hover:text-indigo-800 transition-colors">Ver</Link>
                        </td>
                    </tr>
                    <tr v-if="receivables.data.length === 0">
                        <td colspan="7" class="px-6 py-16 text-center">
                            <p class="text-sm text-gray-500">Nenhuma conta a receber encontrada.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="receivables.last_page > 1" class="mt-5 flex justify-end gap-1">
            <template v-for="link in receivables.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                        'rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors'
                    ]"
                    v-html="link.label"
                />
                <span
                    v-else
                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-400"
                    v-html="link.label"
                />
            </template>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 2: Run Vite build to verify no compile errors**

```bash
npm run build 2>&1 | tail -5
```

Expected: `✓ built in Xs`

- [ ] **Step 3: Run full test suite**

```bash
php artisan test
```

Expected: All passing.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Finance/Receivables/Index.vue
git commit -m "feat(finance): add Receivables Index page"
```

---

## Task 10: Frontend — Receivables/Show.vue (with inline payment form)

**Files:**
- Create: `resources/js/Pages/Finance/Receivables/Show.vue`

- [ ] **Step 1: Create the Show page**

```vue
<!-- resources/js/Pages/Finance/Receivables/Show.vue -->
<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        receivable: Object,
        methods: Array,
    },

    data() {
        return {
            form: useForm({
                amount: '',
                method: 'pix',
                paid_at: new Date().toISOString().slice(0, 16),
                notes: '',
            }),
            showPayForm: false,
        }
    },

    computed: {
        balance() {
            return Math.max(0, this.receivable.amount_due - this.receivable.amount_paid).toFixed(2)
        },
    },

    methods: {
        submitPayment() {
            this.form.post(route('receivables.payments.store', this.receivable.id), {
                onSuccess: () => {
                    this.showPayForm = false
                    this.form.reset()
                },
            })
        },
        statusClass(status) {
            return {
                open: 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
                partially_paid: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
                paid: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
                overdue: 'bg-red-50 text-red-700 ring-1 ring-red-200',
            }[status] ?? 'bg-gray-100 text-gray-500'
        },
        statusLabel(status) {
            return {
                open: 'Em aberto',
                partially_paid: 'Parcialmente pago',
                paid: 'Pago',
                overdue: 'Vencido',
            }[status] ?? status
        },
        formatCurrency(value) {
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)
        },
        methodLabel(method) {
            return {
                pix: 'PIX',
                transferencia: 'Transferência',
                dinheiro: 'Dinheiro',
                cheque: 'Cheque',
                boleto: 'Boleto',
            }[method] ?? method
        },
    },
}
</script>

<template>
    <Head title="Conta a Receber" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('receivables.index')" class="text-sm text-gray-500 hover:text-gray-700">← Contas a Receber</Link>
                    <h1 class="text-xl font-semibold text-gray-900">Recebível #{{ receivable.id }}</h1>
                    <span :class="statusClass(receivable.status)" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                        {{ statusLabel(receivable.status) }}
                    </span>
                </div>
                <button
                    v-if="receivable.status !== 'paid'"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition-colors"
                    @click="showPayForm = !showPayForm"
                >
                    Registrar Pagamento
                </button>
            </div>
        </template>

        <!-- Details -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-6">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 lg:col-span-2">
                <h2 class="mb-4 text-sm font-semibold text-gray-700 uppercase tracking-wide">Detalhes</h2>
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs text-gray-500">Cliente</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ receivable.client?.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Frete vinculado</dt>
                        <dd class="text-sm text-gray-900">
                            <Link v-if="receivable.freight_id" :href="route('freights.show', receivable.freight_id)" class="text-indigo-600 hover:text-indigo-800">#{{ receivable.freight_id }}</Link>
                            <span v-else class="text-gray-400">—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Vencimento</dt>
                        <dd class="text-sm text-gray-900">{{ receivable.due_date }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <h2 class="mb-4 text-sm font-semibold text-gray-700 uppercase tracking-wide">Valores</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Total devido</span>
                        <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(receivable.amount_due) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Total pago</span>
                        <span class="text-sm font-semibold text-emerald-600">{{ formatCurrency(receivable.amount_paid) }}</span>
                    </div>
                    <div class="border-t border-gray-100 pt-3 flex justify-between">
                        <span class="text-sm font-medium text-gray-700">Saldo</span>
                        <span class="text-sm font-bold" :class="balance > 0 ? 'text-red-600' : 'text-emerald-600'">
                            {{ formatCurrency(balance) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment form -->
        <div v-if="showPayForm" class="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <h2 class="mb-4 text-sm font-semibold text-gray-700 uppercase tracking-wide">Novo Pagamento</h2>
            <form class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="submitPayment">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Valor (R$)</label>
                    <input
                        v-model="form.amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        :max="balance"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        :class="{ 'border-red-500': form.errors.amount }"
                    />
                    <p v-if="form.errors.amount" class="mt-1 text-xs text-red-600">{{ form.errors.amount }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Forma de pagamento</label>
                    <select
                        v-model="form.method"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        :class="{ 'border-red-500': form.errors.method }"
                    >
                        <option v-for="m in methods" :key="m" :value="m">{{ methodLabel(m) }}</option>
                    </select>
                    <p v-if="form.errors.method" class="mt-1 text-xs text-red-600">{{ form.errors.method }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Data do pagamento</label>
                    <input
                        v-model="form.paid_at"
                        type="datetime-local"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        :class="{ 'border-red-500': form.errors.paid_at }"
                    />
                    <p v-if="form.errors.paid_at" class="mt-1 text-xs text-red-600">{{ form.errors.paid_at }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Observações</label>
                    <input
                        v-model="form.notes"
                        type="text"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                </div>
                <div class="sm:col-span-2 lg:col-span-4 flex gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition-colors disabled:opacity-50"
                    >
                        Confirmar pagamento
                    </button>
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors" @click="showPayForm = false">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>

        <!-- Payment history -->
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Histórico de Pagamentos</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Forma</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Valor</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Obs.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-for="payment in receivable.payments" :key="payment.id" class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ payment.paid_at }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ methodLabel(payment.method) }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium text-gray-900">{{ formatCurrency(payment.amount) }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ payment.notes ?? '—' }}</td>
                    </tr>
                    <tr v-if="receivable.payments.length === 0">
                        <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">Nenhum pagamento registrado.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 2: Add "Recebíveis" nav link**

In `resources/js/Layouts/AuthenticatedLayout.vue`, find the navigation links section and add a link to Recebíveis next to the other nav items. The exact location will match where `Fretes` was added in Epic 4. Look for the `<NavLink>` pattern and add:

```html
<NavLink :href="route('receivables.index')" :active="route().current('receivables.*')">
    Recebíveis
</NavLink>
```

- [ ] **Step 3: Run Vite build**

```bash
npm run build 2>&1 | tail -5
```

Expected: `✓ built in Xs`

- [ ] **Step 4: Run full test suite**

```bash
php artisan test
```

Expected: All passing.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Finance/Receivables/Show.vue \
        resources/js/Layouts/AuthenticatedLayout.vue
git commit -m "feat(finance): add Receivables Show page with inline payment form and nav link"
```

---

## Self-Review Against Spec

| Spec requirement | Covered in |
|---|---|
| Receivable listener on `FreightEnteredAwaitingPayment` | Task 4 |
| Receivables index with filters (client, status, due date) | Task 6 + Task 9 |
| Payment recording screen → `payments` table + updates receivable status | Task 5 + Task 7 + Task 10 |
| Receivable full-paid triggers `AwaitingPayment → Completed` | Task 5 (`RecordPaymentAction`) |
| Overdue detection daily job | Task 8 |
| Tenant isolation (RLS) | Task 1 (RLS migration) |
| Role gates (Admin+Financial only) | Task 3 (policies) + Task 6/7 tests |

All spec requirements covered. No placeholders found.
