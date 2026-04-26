# Epic 05 — Finance: Expenses, Fuel Records & Maintenance Records

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Branch:** `epic/05-finance-expenses` (shared with Epic 07)

**Goal:** Build the Finance: Expenses, Fuel Records, and Maintenance Records module. Expenses use a dynamic tenant-scoped tag system (`expense_categories` table) — users can pick from existing tags or create new ones inline. Fuel and maintenance are straightforward CRUD. No action layer: controllers are thin direct CRUD (fuel records compute `total_cost` inline).

**Architecture:** Four new tables (`expense_categories`, `expenses`, `fuel_records`, `maintenance_records`). `expense_categories` are seeded with defaults on company creation. Color palette auto-assigned (12-slot rotating). Frontend combobox for categories creates missing categories via a dedicated POST endpoint before submitting the expense form. Index filters via `spatie/laravel-query-builder`.

**Tech Stack:** Laravel 11, Inertia.js, Vue 3 Options API, PostgreSQL, spatie/laravel-query-builder, spatie/laravel-permission (Admin/Financial only — Operators have no Finance access), TailwindCSS.

---

## Architectural Decisions

| Decision | Choice | Reason |
|---|---|---|
| `expense_categories` | Tenant-scoped table, not enum | Users need custom categories; categories belong to the company |
| Category color | Auto-assigned from 12-slot palette (`count % 12`) | Consistent visual identity without user burden |
| Default categories | Seeded in `RegisterCompanyAction` | Every new company starts with usable defaults |
| Expense filter | `expense_category_id` FK | Enables reporting by category |
| `maintenance_records.type` | DB enum: `preventive`, `corrective`, `emergency`, `routine` | Small known set; constrained for reporting |
| `fuel_records.total_cost` | Stored, computed in controller | Audit record; cheap aggregation queries |
| `fuel_records.fueled_at` | `date` (not timestamp) | Per design doc |
| `fuel_records.odometer_km` | **Nullable** | Operators often don't track odometer per fill-up (pushing back on design doc which omits null) |
| Action classes | **None for Epic 05** | All writes are trivial CRUD; `total_cost` is one inline expression |
| Role access | Admin + Financial: full CRUD. **Operator: no access** | Finance data is Financial-only per design doc |
| Index filters | `spatie/laravel-query-builder` | Per design doc; requires install |

---

## File Map

### New files
| Path | Responsibility |
|---|---|
| `database/migrations/2026_04_25_000001_create_expense_categories_table.php` | Expense categories schema |
| `database/migrations/2026_04_25_000002_create_expenses_table.php` | Expenses schema + CHECK constraint |
| `database/migrations/2026_04_25_000003_create_fuel_records_table.php` | Fuel records schema |
| `database/migrations/2026_04_25_000004_create_maintenance_records_table.php` | Maintenance records schema |
| `database/migrations/rls/2026_04_25_000005_enable_rls_on_expense_tables.php` | RLS on all four tables |
| `database/factories/Finance/ExpenseCategoryFactory.php` | Test factory |
| `database/factories/Finance/ExpenseFactory.php` | Test factory |
| `database/factories/Finance/FuelRecordFactory.php` | Test factory |
| `database/factories/Finance/MaintenanceRecordFactory.php` | Test factory |
| `app/Modules/Finance/Models/ExpenseCategory.php` | Tenant-scoped tag model with color palette |
| `app/Modules/Finance/Models/Expense.php` | BelongsToCompany + category/vehicle/freight relations |
| `app/Modules/Finance/Models/FuelRecord.php` | BelongsToCompany + vehicle/driver/freight relations |
| `app/Modules/Finance/Models/MaintenanceRecord.php` | BelongsToCompany + vehicle relation |
| `app/Modules/Finance/Actions/SeedExpenseCategoriesAction.php` | Seeds default categories for a new company |
| `app/Modules/Finance/Policies/ExpenseCategoryPolicy.php` | Admin+Financial create; no public management UI |
| `app/Modules/Finance/Policies/ExpensePolicy.php` | Admin+Financial full CRUD |
| `app/Modules/Finance/Policies/FuelRecordPolicy.php` | Admin+Financial full CRUD |
| `app/Modules/Finance/Policies/MaintenanceRecordPolicy.php` | Admin+Financial full CRUD |
| `app/Modules/Finance/Http/Requests/StoreExpenseRequest.php` | |
| `app/Modules/Finance/Http/Requests/UpdateExpenseRequest.php` | |
| `app/Modules/Finance/Http/Requests/StoreFuelRecordRequest.php` | |
| `app/Modules/Finance/Http/Requests/UpdateFuelRecordRequest.php` | |
| `app/Modules/Finance/Http/Requests/StoreMaintenanceRecordRequest.php` | |
| `app/Modules/Finance/Http/Requests/UpdateMaintenanceRecordRequest.php` | |
| `app/Modules/Finance/Http/Controllers/ExpenseCategoryController.php` | POST only — creates category on-demand from frontend |
| `app/Modules/Finance/Http/Controllers/ExpenseController.php` | index, create, store, edit, update, destroy |
| `app/Modules/Finance/Http/Controllers/FuelRecordController.php` | index, create, store, edit, update, destroy |
| `app/Modules/Finance/Http/Controllers/MaintenanceRecordController.php` | index, create, store, edit, update, destroy |
| `resources/js/Pages/Finance/Expenses/Index.vue` | Filterable table with category chips |
| `resources/js/Pages/Finance/Expenses/Form.vue` | Create/edit with category combobox (create-on-demand) |
| `resources/js/Pages/Finance/FuelRecords/Index.vue` | |
| `resources/js/Pages/Finance/FuelRecords/Form.vue` | |
| `resources/js/Pages/Finance/Maintenance/Index.vue` | |
| `resources/js/Pages/Finance/Maintenance/Form.vue` | |
| `tests/Feature/Finance/ExpenseCategoryControllerTest.php` | |
| `tests/Feature/Finance/ExpenseControllerTest.php` | |
| `tests/Feature/Finance/FuelRecordControllerTest.php` | |
| `tests/Feature/Finance/MaintenanceRecordControllerTest.php` | |

### Modified files
| Path | Change |
|---|---|
| `app/Modules/Identity/Actions/RegisterCompanyAction.php` | Inject + call `SeedExpenseCategoriesAction` |
| `app/Providers/AppServiceProvider.php` | Register 4 new policies |
| `routes/web.php` | Add 4 resource routes + POST expense-categories |
| `resources/js/Layouts/AuthenticatedLayout.vue` | Add Finance sub-nav |

---

## Task 0: Install required packages

- [ ] **Step 1: Install spatie/laravel-query-builder**

```bash
composer require spatie/laravel-query-builder
```

Expected: Package installed, no conflicts.

- [ ] **Step 2: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: install spatie/laravel-query-builder"
```

---

## Task 1: Migrations

- [ ] **Step 1: Create expense_categories migration**

```php
<?php
// database/migrations/2026_04_25_000001_create_expense_categories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('name', 100);
            $table->char('color', 7);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
```

- [ ] **Step 2: Create expenses migration**

```php
<?php
// database/migrations/2026_04_25_000002_create_expenses_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('expense_category_id')->constrained('expense_categories');
            $table->decimal('amount', 12, 2);
            $table->date('incurred_on');
            $table->text('description')->nullable();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('freight_id')->nullable()->constrained('freights')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'expense_category_id']);
            $table->index(['company_id', 'incurred_on']);
            $table->index(['company_id', 'vehicle_id']);
        });

        DB::statement(
            'ALTER TABLE expenses ADD CONSTRAINT expenses_vehicle_or_freight_check '
            . 'CHECK (NOT (vehicle_id IS NOT NULL AND freight_id IS NOT NULL))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
```

- [ ] **Step 3: Create fuel_records migration**

```php
<?php
// database/migrations/2026_04_25_000003_create_fuel_records_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('freight_id')->nullable()->constrained('freights')->nullOnDelete();
            $table->decimal('liters', 8, 3);
            $table->decimal('price_per_liter', 8, 4);
            $table->decimal('total_cost', 12, 2);
            $table->integer('odometer_km')->nullable();
            $table->date('fueled_at');
            $table->string('station', 150)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'vehicle_id', 'fueled_at']);
            $table->index(['company_id', 'fueled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_records');
    }
};
```

- [ ] **Step 4: Create maintenance_records migration**

```php
<?php
// database/migrations/2026_04_25_000004_create_maintenance_records_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->enum('type', ['preventive', 'corrective', 'emergency', 'routine']);
            $table->text('description');
            $table->decimal('cost', 12, 2);
            $table->integer('odometer_km')->nullable();
            $table->date('performed_on');
            $table->string('provider', 150)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'vehicle_id', 'performed_on']);
            $table->index(['company_id', 'performed_on']);
            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
```

- [ ] **Step 5: Create RLS migration**

```php
<?php
// database/migrations/rls/2026_04_25_000005_enable_rls_on_expense_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE expense_categories ENABLE ROW LEVEL SECURITY;
            ALTER TABLE expense_categories FORCE ROW LEVEL SECURITY;
            CREATE POLICY expense_categories_company_isolation ON expense_categories
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

            ALTER TABLE expenses ENABLE ROW LEVEL SECURITY;
            ALTER TABLE expenses FORCE ROW LEVEL SECURITY;
            CREATE POLICY expenses_company_isolation ON expenses
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

            ALTER TABLE fuel_records ENABLE ROW LEVEL SECURITY;
            ALTER TABLE fuel_records FORCE ROW LEVEL SECURITY;
            CREATE POLICY fuel_records_company_isolation ON fuel_records
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

            ALTER TABLE maintenance_records ENABLE ROW LEVEL SECURITY;
            ALTER TABLE maintenance_records FORCE ROW LEVEL SECURITY;
            CREATE POLICY maintenance_records_company_isolation ON maintenance_records
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
            DROP POLICY IF EXISTS expense_categories_company_isolation ON expense_categories;
            DROP POLICY IF EXISTS expenses_company_isolation ON expenses;
            DROP POLICY IF EXISTS fuel_records_company_isolation ON fuel_records;
            DROP POLICY IF EXISTS maintenance_records_company_isolation ON maintenance_records;
            ALTER TABLE expense_categories DISABLE ROW LEVEL SECURITY;
            ALTER TABLE expenses DISABLE ROW LEVEL SECURITY;
            ALTER TABLE fuel_records DISABLE ROW LEVEL SECURITY;
            ALTER TABLE maintenance_records DISABLE ROW LEVEL SECURITY;
        SQL);
    }
};
```

- [ ] **Step 6: Run migrations**

```bash
php artisan migrate
```

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_04_25_000001_create_expense_categories_table.php \
        database/migrations/2026_04_25_000002_create_expenses_table.php \
        database/migrations/2026_04_25_000003_create_fuel_records_table.php \
        database/migrations/2026_04_25_000004_create_maintenance_records_table.php \
        database/migrations/rls/2026_04_25_000005_enable_rls_on_expense_tables.php
git commit -m "feat(finance): add expense_categories, expenses, fuel_records, maintenance_records migrations with RLS"
```

---

## Task 2: Models and Factories

- [ ] **Step 1: Create ExpenseCategory model**

```php
<?php
// app/Modules/Finance/Models/ExpenseCategory.php

namespace App\Modules\Finance\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Finance\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use BelongsToCompany, HasFactory;

    public const COLOR_PALETTE = [
        '#EF4444', '#F97316', '#EAB308', '#22C55E',
        '#14B8A6', '#3B82F6', '#6366F1', '#8B5CF6',
        '#EC4899', '#06B6D4', '#84CC16', '#F59E0B',
    ];

    public const DEFAULTS = [
        'Combustível', 'Pedágio', 'Manutenção', 'Seguro', 'Administrativo',
    ];

    protected $fillable = ['company_id', 'name', 'color'];

    protected static function newFactory(): ExpenseCategoryFactory
    {
        return ExpenseCategoryFactory::new();
    }

    public static function nextColor(int $existingCount): string
    {
        return self::COLOR_PALETTE[$existingCount % count(self::COLOR_PALETTE)];
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
```

- [ ] **Step 2: Create Expense model**

```php
<?php
// app/Modules/Finance/Models/Expense.php

namespace App\Modules\Finance\Models;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\Models\Freight;
use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Finance\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'expense_category_id', 'amount',
        'incurred_on', 'description', 'vehicle_id', 'freight_id',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'incurred_on' => 'date',
    ];

    protected static function newFactory(): ExpenseFactory
    {
        return ExpenseFactory::new();
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Freight, $this> */
    public function freight(): BelongsTo
    {
        return $this->belongsTo(Freight::class);
    }
}
```

- [ ] **Step 3: Create FuelRecord model**

```php
<?php
// app/Modules/Finance/Models/FuelRecord.php

namespace App\Modules\Finance\Models;

use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\Models\Freight;
use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Finance\FuelRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelRecord extends Model
{
    /** @use HasFactory<FuelRecordFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'vehicle_id', 'driver_id', 'freight_id',
        'liters', 'price_per_liter', 'total_cost',
        'odometer_km', 'fueled_at', 'station',
    ];

    protected $casts = [
        'liters'          => 'decimal:3',
        'price_per_liter' => 'decimal:4',
        'total_cost'      => 'decimal:2',
        'fueled_at'       => 'date',
    ];

    protected static function newFactory(): FuelRecordFactory
    {
        return FuelRecordFactory::new();
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /** @return BelongsTo<Freight, $this> */
    public function freight(): BelongsTo
    {
        return $this->belongsTo(Freight::class);
    }
}
```

- [ ] **Step 4: Create MaintenanceRecord model**

```php
<?php
// app/Modules/Finance/Models/MaintenanceRecord.php

namespace App\Modules\Finance\Models;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Finance\MaintenanceRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRecord extends Model
{
    /** @use HasFactory<MaintenanceRecordFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'vehicle_id', 'type', 'description',
        'cost', 'odometer_km', 'performed_on', 'provider',
    ];

    protected $casts = [
        'cost'         => 'decimal:2',
        'performed_on' => 'date',
    ];

    protected static function newFactory(): MaintenanceRecordFactory
    {
        return MaintenanceRecordFactory::new();
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
```

- [ ] **Step 5: Create factories**

```php
<?php
// database/factories/Finance/ExpenseCategoryFactory.php

namespace Database\Factories\Finance;

use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExpenseCategory> */
class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name'       => fake()->unique()->word(),
            'color'      => fake()->randomElement(ExpenseCategory::COLOR_PALETTE),
        ];
    }
}
```

```php
<?php
// database/factories/Finance/ExpenseFactory.php

namespace Database\Factories\Finance;

use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Expense> */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'company_id'          => $company->id,
            'expense_category_id' => ExpenseCategory::factory()->create(['company_id' => $company->id])->id,
            'amount'              => fake()->randomFloat(2, 10, 5000),
            'incurred_on'         => fake()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'description'         => null,
            'vehicle_id'          => null,
            'freight_id'          => null,
        ];
    }
}
```

```php
<?php
// database/factories/Finance/FuelRecordFactory.php

namespace Database\Factories\Finance;

use App\Modules\Finance\Models\FuelRecord;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FuelRecord> */
class FuelRecordFactory extends Factory
{
    protected $model = FuelRecord::class;

    public function definition(): array
    {
        $company = Company::factory()->create();
        $liters  = fake()->randomFloat(3, 20, 400);
        $price   = fake()->randomFloat(4, 5.50, 8.00);

        return [
            'company_id'      => $company->id,
            'vehicle_id'      => Vehicle::factory()->create(['company_id' => $company->id])->id,
            'driver_id'       => null,
            'freight_id'      => null,
            'liters'          => $liters,
            'price_per_liter' => $price,
            'total_cost'      => round($liters * $price, 2),
            'odometer_km'     => null,
            'fueled_at'       => fake()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'station'         => null,
        ];
    }
}
```

```php
<?php
// database/factories/Finance/MaintenanceRecordFactory.php

namespace Database\Factories\Finance;

use App\Modules\Finance\Models\MaintenanceRecord;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceRecord> */
class MaintenanceRecordFactory extends Factory
{
    protected $model = MaintenanceRecord::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'company_id'   => $company->id,
            'vehicle_id'   => Vehicle::factory()->create(['company_id' => $company->id])->id,
            'type'         => fake()->randomElement(['preventive', 'corrective', 'emergency', 'routine']),
            'description'  => fake()->sentence(),
            'cost'         => fake()->randomFloat(2, 50, 10000),
            'odometer_km'  => null,
            'performed_on' => fake()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'provider'     => null,
        ];
    }
}
```

- [ ] **Step 6: Run full test suite**

```bash
php artisan test
```

Expected: All previously passing tests still pass.

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Finance/Models/ExpenseCategory.php \
        app/Modules/Finance/Models/Expense.php \
        app/Modules/Finance/Models/FuelRecord.php \
        app/Modules/Finance/Models/MaintenanceRecord.php \
        database/factories/Finance/
git commit -m "feat(finance): add ExpenseCategory, Expense, FuelRecord, MaintenanceRecord models with factories"
```

---

## Task 3: SeedExpenseCategoriesAction + Company Registration Hook

- [ ] **Step 1: Create SeedExpenseCategoriesAction**

```php
<?php
// app/Modules/Finance/Actions/SeedExpenseCategoriesAction.php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Tenancy\Models\Company;

class SeedExpenseCategoriesAction
{
    public function handle(Company $company): void
    {
        foreach (ExpenseCategory::DEFAULTS as $index => $name) {
            ExpenseCategory::firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['color' => ExpenseCategory::COLOR_PALETTE[$index % count(ExpenseCategory::COLOR_PALETTE)]]
            );
        }
    }
}
```

- [ ] **Step 2: Wire into RegisterCompanyAction**

Modify `app/Modules/Identity/Actions/RegisterCompanyAction.php`:

```php
// Add import
use App\Modules\Finance\Actions\SeedExpenseCategoriesAction;

// Update constructor
public function __construct(
    private SeedCompanyRolesAction $seedRoles,
    private SeedExpenseCategoriesAction $seedCategories,
) {}

// In handle(), after $this->seedRoles->handle($company):
$this->seedCategories->handle($company);
```

- [ ] **Step 3: Write test for seeding**

```php
// In tests/Feature/Identity/ or a new tests/Feature/Finance/SeedExpenseCategoriesTest.php

public function test_default_categories_seeded_on_company_creation(): void
{
    $user = $this->makeUserWithRole('Admin');

    $categories = ExpenseCategory::where('company_id', $user->company_id)->get();

    $this->assertCount(count(ExpenseCategory::DEFAULTS), $categories);
    $this->assertContains('Combustível', $categories->pluck('name')->toArray());
    $this->assertContains('Pedágio', $categories->pluck('name')->toArray());
}

public function test_each_default_category_has_unique_color(): void
{
    $user = $this->makeUserWithRole('Admin');

    $colors = ExpenseCategory::where('company_id', $user->company_id)->pluck('color');

    $this->assertEquals($colors->count(), $colors->unique()->count());
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test
```

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Actions/SeedExpenseCategoriesAction.php \
        app/Modules/Identity/Actions/RegisterCompanyAction.php
git commit -m "feat(finance): seed default expense categories on company registration"
```

---

## Task 4: Policies + AppServiceProvider Registration

- [ ] **Step 1: Create ExpenseCategoryPolicy**

```php
<?php
// app/Modules/Finance/Policies/ExpenseCategoryPolicy.php

namespace App\Modules\Finance\Policies;

use App\Models\User;

class ExpenseCategoryPolicy
{
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }
}
```

- [ ] **Step 2: Create ExpensePolicy**

```php
<?php
// app/Modules/Finance/Policies/ExpensePolicy.php

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\Expense;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $expense->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $expense->company_id;
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $expense->company_id;
    }
}
```

- [ ] **Step 3: Create FuelRecordPolicy**

Same structure as ExpensePolicy but for `FuelRecord`.

```php
<?php
// app/Modules/Finance/Policies/FuelRecordPolicy.php

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\FuelRecord;

class FuelRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function view(User $user, FuelRecord $fuelRecord): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $fuelRecord->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function update(User $user, FuelRecord $fuelRecord): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $fuelRecord->company_id;
    }

    public function delete(User $user, FuelRecord $fuelRecord): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $fuelRecord->company_id;
    }
}
```

- [ ] **Step 4: Create MaintenanceRecordPolicy**

Same structure as above for `MaintenanceRecord`.

```php
<?php
// app/Modules/Finance/Policies/MaintenanceRecordPolicy.php

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\MaintenanceRecord;

class MaintenanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function view(User $user, MaintenanceRecord $record): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $record->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function update(User $user, MaintenanceRecord $record): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $record->company_id;
    }

    public function delete(User $user, MaintenanceRecord $record): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $record->company_id;
    }
}
```

- [ ] **Step 5: Register in AppServiceProvider**

Add imports and `Gate::policy()` calls:

```php
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Finance\Models\FuelRecord;
use App\Modules\Finance\Models\MaintenanceRecord;
use App\Modules\Finance\Policies\ExpenseCategoryPolicy;
use App\Modules\Finance\Policies\ExpensePolicy;
use App\Modules\Finance\Policies\FuelRecordPolicy;
use App\Modules\Finance\Policies\MaintenanceRecordPolicy;

// in boot():
Gate::policy(ExpenseCategory::class, ExpenseCategoryPolicy::class);
Gate::policy(Expense::class, ExpensePolicy::class);
Gate::policy(FuelRecord::class, FuelRecordPolicy::class);
Gate::policy(MaintenanceRecord::class, MaintenanceRecordPolicy::class);
```

- [ ] **Step 6: Run full test suite**

```bash
php artisan test
```

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Finance/Policies/ app/Providers/AppServiceProvider.php
git commit -m "feat(finance): add Finance module policies (Admin+Financial only)"
```

---

## Task 5: ExpenseCategoryController (TDD)

**Purpose:** Provide a POST endpoint for creating new expense categories on-demand from the expense form combobox. Returns the created (or found) category as JSON.

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Finance/ExpenseCategoryControllerTest.php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class ExpenseCategoryControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_financial_can_create_new_category(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $response = $this->actingAsTenant($user)->postJson('/expense-categories', [
            'name' => 'Nova Categoria',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'name', 'color']);
        $this->assertDatabaseHas('expense_categories', [
            'company_id' => $user->company_id,
            'name'       => 'Nova Categoria',
        ]);
    }

    public function test_creating_existing_name_returns_existing_category(): void
    {
        $user     = $this->makeUserWithRole('Financial');
        $existing = ExpenseCategory::factory()->create([
            'company_id' => $user->company_id,
            'name'       => 'Combustível',
        ]);

        $response = $this->actingAsTenant($user)->postJson('/expense-categories', [
            'name' => 'Combustível',
        ]);

        $response->assertOk();
        $this->assertEquals($existing->id, $response->json('id'));
        $this->assertEquals(1, ExpenseCategory::where(['company_id' => $user->company_id, 'name' => 'Combustível'])->count());
    }

    public function test_operator_cannot_create_category(): void
    {
        $user = $this->makeUserWithRole('Operator');

        $this->actingAsTenant($user)->postJson('/expense-categories', ['name' => 'Test'])->assertForbidden();
    }

    public function test_color_is_auto_assigned_from_palette(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $response = $this->actingAsTenant($user)->postJson('/expense-categories', ['name' => 'Test Cat']);

        $color = $response->json('color');
        $this->assertContains($color, ExpenseCategory::COLOR_PALETTE);
    }

    public function test_category_belongs_to_current_company(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        $this->actingAsTenant($userA)->postJson('/expense-categories', ['name' => 'Cat X']);

        $this->assertDatabaseMissing('expense_categories', ['company_id' => $userB->company_id, 'name' => 'Cat X']);
    }
}
```

- [ ] **Step 2: Run tests — expect failure**

```bash
php artisan test tests/Feature/Finance/ExpenseCategoryControllerTest.php
```

- [ ] **Step 3: Add route to web.php**

```php
use App\Modules\Finance\Http\Controllers\ExpenseCategoryController;
use App\Modules\Finance\Http\Controllers\ExpenseController;
use App\Modules\Finance\Http\Controllers\FuelRecordController;
use App\Modules\Finance\Http\Controllers\MaintenanceRecordController;

Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
Route::resource('expenses', ExpenseController::class)->except('show');
Route::resource('fuel-records', FuelRecordController::class)->except('show');
Route::resource('maintenance-records', MaintenanceRecordController::class)->except('show');
```

- [ ] **Step 4: Create ExpenseCategoryController**

```php
<?php
// app/Modules/Finance/Http/Controllers/ExpenseCategoryController.php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ExpenseCategory::class);

        $request->validate(['name' => ['required', 'string', 'max:100']]);

        $count    = ExpenseCategory::count();
        $color    = ExpenseCategory::nextColor($count);
        $category = ExpenseCategory::firstOrCreate(
            ['name' => $request->name],
            ['color' => $color]
        );

        $status = $category->wasRecentlyCreated ? 201 : 200;

        return response()->json($category, $status);
    }
}
```

- [ ] **Step 5: Run tests — expect pass**

```bash
php artisan test tests/Feature/Finance/ExpenseCategoryControllerTest.php
```

- [ ] **Step 6: Run full test suite**

```bash
php artisan test
```

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Finance/Http/Controllers/ExpenseCategoryController.php \
        tests/Feature/Finance/ExpenseCategoryControllerTest.php \
        routes/web.php
git commit -m "feat(finance): add ExpenseCategoryController for on-demand category creation"
```

---

## Task 6: Expenses CRUD (TDD)

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/Finance/ExpenseControllerTest.php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class ExpenseControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_financial_can_list_expenses(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $this->actingAsTenant($user)->get('/expenses')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Finance/Expenses/Index'));
    }

    public function test_operator_cannot_access_expenses(): void
    {
        $user = $this->makeUserWithRole('Operator');

        $this->actingAsTenant($user)->get('/expenses')->assertForbidden();
    }

    public function test_index_does_not_leak_other_company_expenses(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        Expense::factory()->create(['company_id' => $userA->company_id]);
        Expense::factory()->create(['company_id' => $userB->company_id]);

        $this->actingAsTenant($userA)->get('/expenses')
            ->assertInertia(fn ($page) => $page->has('expenses.data', 1));
    }

    public function test_index_filters_by_category(): void
    {
        $user  = $this->makeUserWithRole('Financial');
        $catA  = ExpenseCategory::factory()->create(['company_id' => $user->company_id]);
        $catB  = ExpenseCategory::factory()->create(['company_id' => $user->company_id]);
        Expense::factory()->create(['company_id' => $user->company_id, 'expense_category_id' => $catA->id]);
        Expense::factory()->create(['company_id' => $user->company_id, 'expense_category_id' => $catB->id]);

        $this->actingAsTenant($user)->get("/expenses?filter[expense_category_id]={$catA->id}")
            ->assertInertia(fn ($page) => $page->has('expenses.data', 1));
    }

    public function test_financial_can_create_expense(): void
    {
        $user     = $this->makeUserWithRole('Financial');
        $category = ExpenseCategory::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->post('/expenses', [
            'expense_category_id' => $category->id,
            'amount'              => '150.00',
            'incurred_on'         => '2026-04-24',
        ]);

        $response->assertRedirect('/expenses');
        $this->assertDatabaseHas('expenses', [
            'company_id'          => $user->company_id,
            'expense_category_id' => $category->id,
            'amount'              => '150.00',
        ]);
    }

    public function test_expense_cannot_link_both_vehicle_and_freight(): void
    {
        $user     = $this->makeUserWithRole('Financial');
        $category = ExpenseCategory::factory()->create(['company_id' => $user->company_id]);
        $vehicle  = Vehicle::factory()->create(['company_id' => $user->company_id]);
        $freight  = Freight::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->post('/expenses', [
            'expense_category_id' => $category->id,
            'amount'              => '100.00',
            'incurred_on'         => '2026-04-24',
            'vehicle_id'          => $vehicle->id,
            'freight_id'          => $freight->id,
        ])->assertSessionHasErrors();
    }

    public function test_financial_can_update_expense(): void
    {
        $user     = $this->makeUserWithRole('Financial');
        $expense  = Expense::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->put("/expenses/{$expense->id}", [
            'expense_category_id' => $expense->expense_category_id,
            'amount'              => '999.00',
            'incurred_on'         => $expense->incurred_on->format('Y-m-d'),
        ]);

        $response->assertRedirect('/expenses');
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'amount' => '999.00']);
    }

    public function test_financial_cannot_update_other_company_expense(): void
    {
        $user  = $this->makeUserWithRole('Financial');
        $other = Expense::factory()->create();

        $this->actingAsTenant($user)->put("/expenses/{$other->id}", [
            'expense_category_id' => $other->expense_category_id,
            'amount'              => '1.00',
            'incurred_on'         => '2026-04-24',
        ])->assertForbidden();
    }

    public function test_financial_can_delete_expense(): void
    {
        $user    = $this->makeUserWithRole('Financial');
        $expense = Expense::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->delete("/expenses/{$expense->id}")
            ->assertRedirect('/expenses');
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }
}
```

- [ ] **Step 2: Run tests — expect failure**

```bash
php artisan test tests/Feature/Finance/ExpenseControllerTest.php
```

- [ ] **Step 3: Create StoreExpenseRequest**

```php
<?php
// app/Modules/Finance/Http/Requests/StoreExpenseRequest.php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'incurred_on'         => ['required', 'date'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'vehicle_id'          => ['nullable', 'exists:vehicles,id'],
            'freight_id'          => ['nullable', 'exists:freights,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->filled('vehicle_id') && $this->filled('freight_id')) {
                $v->errors()->add('vehicle_id', 'Informe o veículo ou o frete, não ambos.');
            }
        });
    }
}
```

- [ ] **Step 4: Create UpdateExpenseRequest**

```php
<?php
// app/Modules/Finance/Http/Requests/UpdateExpenseRequest.php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'incurred_on'         => ['required', 'date'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'vehicle_id'          => ['nullable', 'exists:vehicles,id'],
            'freight_id'          => ['nullable', 'exists:freights,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->filled('vehicle_id') && $this->filled('freight_id')) {
                $v->errors()->add('vehicle_id', 'Informe o veículo ou o frete, não ambos.');
            }
        });
    }
}
```

- [ ] **Step 5: Create ExpenseController**

```php
<?php
// app/Modules/Finance/Http/Controllers/ExpenseController.php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\StoreExpenseRequest;
use App\Modules\Finance\Http\Requests\UpdateExpenseRequest;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\Models\Freight;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ExpenseController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Expense::class);

        $expenses = QueryBuilder::for(Expense::class)
            ->allowedFilters([
                AllowedFilter::exact('expense_category_id'),
                AllowedFilter::exact('vehicle_id'),
                AllowedFilter::exact('freight_id'),
                AllowedFilter::scope('date_from', 'whereDate', 'incurred_on', '>='),
                AllowedFilter::callback('date_from', fn ($q, $v) => $q->whereDate('incurred_on', '>=', $v)),
                AllowedFilter::callback('date_to', fn ($q, $v) => $q->whereDate('incurred_on', '<=', $v)),
            ])
            ->with(['expenseCategory', 'vehicle', 'freight'])
            ->orderByDesc('incurred_on')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Finance/Expenses/Index', [
            'expenses'   => $expenses,
            'categories' => ExpenseCategory::orderBy('name')->get(['id', 'name', 'color']),
            'vehicles'   => Vehicle::orderBy('license_plate')->get(['id', 'license_plate']),
            'filters'    => request()->query(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Expense::class);

        return Inertia::render('Finance/Expenses/Form', [
            'categories' => ExpenseCategory::orderBy('name')->get(['id', 'name', 'color']),
            'vehicles'   => Vehicle::orderBy('license_plate')->get(['id', 'license_plate']),
            'freights'   => Freight::orderByDesc('created_at')->get(['id', 'origin', 'destination']),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        Expense::create($request->validated());

        return redirect()->route('expenses.index')->with('success', 'Despesa registrada.');
    }

    public function edit(Expense $expense): Response
    {
        $this->authorize('update', $expense);

        return Inertia::render('Finance/Expenses/Form', [
            'expense'    => $expense,
            'categories' => ExpenseCategory::orderBy('name')->get(['id', 'name', 'color']),
            'vehicles'   => Vehicle::orderBy('license_plate')->get(['id', 'license_plate']),
            'freights'   => Freight::orderByDesc('created_at')->get(['id', 'origin', 'destination']),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        $expense->update($request->validated());

        return redirect()->route('expenses.index')->with('success', 'Despesa atualizada.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'Despesa removida.');
    }
}
```

- [ ] **Step 6: Run tests — expect pass**

```bash
php artisan test tests/Feature/Finance/ExpenseControllerTest.php
```

- [ ] **Step 7: Run full test suite**

```bash
php artisan test
```

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Finance/Http/Requests/StoreExpenseRequest.php \
        app/Modules/Finance/Http/Requests/UpdateExpenseRequest.php \
        app/Modules/Finance/Http/Controllers/ExpenseController.php \
        tests/Feature/Finance/ExpenseControllerTest.php
git commit -m "feat(finance): add Expenses CRUD"
```

---

## Task 7: Fuel Records CRUD (TDD)

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/Finance/FuelRecordControllerTest.php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Models\FuelRecord;
use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class FuelRecordControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_financial_can_list_fuel_records(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $this->actingAsTenant($user)->get('/fuel-records')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Finance/FuelRecords/Index'));
    }

    public function test_operator_cannot_access_fuel_records(): void
    {
        $user = $this->makeUserWithRole('Operator');

        $this->actingAsTenant($user)->get('/fuel-records')->assertForbidden();
    }

    public function test_index_does_not_leak_other_company_records(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        FuelRecord::factory()->create(['company_id' => $userA->company_id]);
        FuelRecord::factory()->create();

        $this->actingAsTenant($userA)->get('/fuel-records')
            ->assertInertia(fn ($page) => $page->has('fuelRecords.data', 1));
    }

    public function test_financial_can_create_fuel_record(): void
    {
        $user    = $this->makeUserWithRole('Financial');
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->post('/fuel-records', [
            'vehicle_id'      => $vehicle->id,
            'liters'          => '80.500',
            'price_per_liter' => '6.0000',
            'fueled_at'       => '2026-04-24',
        ]);

        $response->assertRedirect('/fuel-records');
        $this->assertDatabaseHas('fuel_records', [
            'company_id' => $user->company_id,
            'vehicle_id' => $vehicle->id,
            'total_cost' => bcmul('80.500', '6.0000', 2),
        ]);
    }

    public function test_total_cost_is_computed_from_liters_and_price(): void
    {
        $user    = $this->makeUserWithRole('Financial');
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->post('/fuel-records', [
            'vehicle_id'      => $vehicle->id,
            'liters'          => '33.333',
            'price_per_liter' => '5.9990',
            'fueled_at'       => '2026-04-24',
        ]);

        $expected = bcmul('33.333', '5.9990', 2);
        $this->assertDatabaseHas('fuel_records', ['total_cost' => $expected]);
    }

    public function test_financial_can_update_fuel_record(): void
    {
        $user   = $this->makeUserWithRole('Financial');
        $record = FuelRecord::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->put("/fuel-records/{$record->id}", [
            'vehicle_id'      => $record->vehicle_id,
            'liters'          => '100.000',
            'price_per_liter' => '7.0000',
            'fueled_at'       => $record->fueled_at->format('Y-m-d'),
        ])->assertRedirect('/fuel-records');

        $this->assertDatabaseHas('fuel_records', ['id' => $record->id, 'total_cost' => '700.00']);
    }

    public function test_financial_can_delete_fuel_record(): void
    {
        $user   = $this->makeUserWithRole('Financial');
        $record = FuelRecord::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->delete("/fuel-records/{$record->id}")
            ->assertRedirect('/fuel-records');
        $this->assertDatabaseMissing('fuel_records', ['id' => $record->id]);
    }

    public function test_financial_cannot_update_other_company_record(): void
    {
        $user  = $this->makeUserWithRole('Financial');
        $other = FuelRecord::factory()->create();

        $this->actingAsTenant($user)->put("/fuel-records/{$other->id}", [
            'vehicle_id'      => $other->vehicle_id,
            'liters'          => '10.000',
            'price_per_liter' => '5.0000',
            'fueled_at'       => '2026-04-24',
        ])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run tests — expect failure**

```bash
php artisan test tests/Feature/Finance/FuelRecordControllerTest.php
```

- [ ] **Step 3: Create StoreFuelRecordRequest and UpdateFuelRecordRequest**

```php
<?php
// app/Modules/Finance/Http/Requests/StoreFuelRecordRequest.php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFuelRecordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'vehicle_id'      => ['required', 'exists:vehicles,id'],
            'driver_id'       => ['nullable', 'exists:drivers,id'],
            'freight_id'      => ['nullable', 'exists:freights,id'],
            'liters'          => ['required', 'numeric', 'min:0.001'],
            'price_per_liter' => ['required', 'numeric', 'min:0.0001'],
            'odometer_km'     => ['nullable', 'integer', 'min:0'],
            'fueled_at'       => ['required', 'date'],
            'station'         => ['nullable', 'string', 'max:150'],
        ];
    }
}
```

`UpdateFuelRecordRequest` — identical rules.

- [ ] **Step 4: Create FuelRecordController**

```php
<?php
// app/Modules/Finance/Http/Controllers/FuelRecordController.php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\StoreFuelRecordRequest;
use App\Modules\Finance\Http\Requests\UpdateFuelRecordRequest;
use App\Modules\Finance\Models\FuelRecord;
use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\Models\Freight;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FuelRecordController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', FuelRecord::class);

        $fuelRecords = QueryBuilder::for(FuelRecord::class)
            ->allowedFilters([
                AllowedFilter::exact('vehicle_id'),
                AllowedFilter::exact('driver_id'),
                AllowedFilter::exact('freight_id'),
                AllowedFilter::callback('date_from', fn ($q, $v) => $q->whereDate('fueled_at', '>=', $v)),
                AllowedFilter::callback('date_to', fn ($q, $v) => $q->whereDate('fueled_at', '<=', $v)),
            ])
            ->with(['vehicle', 'driver', 'freight'])
            ->orderByDesc('fueled_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Finance/FuelRecords/Index', [
            'fuelRecords' => $fuelRecords,
            'vehicles'    => Vehicle::orderBy('license_plate')->get(['id', 'license_plate']),
            'drivers'     => Driver::orderBy('name')->get(['id', 'name']),
            'filters'     => request()->query(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', FuelRecord::class);

        return Inertia::render('Finance/FuelRecords/Form', [
            'vehicles' => Vehicle::orderBy('license_plate')->get(['id', 'license_plate']),
            'drivers'  => Driver::orderBy('name')->get(['id', 'name']),
            'freights' => Freight::orderByDesc('created_at')->get(['id', 'origin', 'destination']),
        ]);
    }

    public function store(StoreFuelRecordRequest $request): RedirectResponse
    {
        $this->authorize('create', FuelRecord::class);

        $data = $request->validated();
        $data['total_cost'] = bcmul((string) $data['liters'], (string) $data['price_per_liter'], 2);

        FuelRecord::create($data);

        return redirect()->route('fuel-records.index')->with('success', 'Abastecimento registrado.');
    }

    public function edit(FuelRecord $fuelRecord): Response
    {
        $this->authorize('update', $fuelRecord);

        return Inertia::render('Finance/FuelRecords/Form', [
            'fuelRecord' => $fuelRecord,
            'vehicles'   => Vehicle::orderBy('license_plate')->get(['id', 'license_plate']),
            'drivers'    => Driver::orderBy('name')->get(['id', 'name']),
            'freights'   => Freight::orderByDesc('created_at')->get(['id', 'origin', 'destination']),
        ]);
    }

    public function update(UpdateFuelRecordRequest $request, FuelRecord $fuelRecord): RedirectResponse
    {
        $this->authorize('update', $fuelRecord);

        $data = $request->validated();
        $data['total_cost'] = bcmul((string) $data['liters'], (string) $data['price_per_liter'], 2);

        $fuelRecord->update($data);

        return redirect()->route('fuel-records.index')->with('success', 'Abastecimento atualizado.');
    }

    public function destroy(FuelRecord $fuelRecord): RedirectResponse
    {
        $this->authorize('delete', $fuelRecord);

        $fuelRecord->delete();

        return redirect()->route('fuel-records.index')->with('success', 'Abastecimento removido.');
    }
}
```

- [ ] **Step 5: Run tests — expect pass**

```bash
php artisan test tests/Feature/Finance/FuelRecordControllerTest.php
```

- [ ] **Step 6: Run full test suite and commit**

```bash
php artisan test
git add app/Modules/Finance/Http/Requests/StoreFuelRecordRequest.php \
        app/Modules/Finance/Http/Requests/UpdateFuelRecordRequest.php \
        app/Modules/Finance/Http/Controllers/FuelRecordController.php \
        tests/Feature/Finance/FuelRecordControllerTest.php
git commit -m "feat(finance): add Fuel Records CRUD with inline total_cost computation"
```

---

## Task 8: Maintenance Records CRUD (TDD)

Follows the same pattern as Task 7. Abbreviated — write test first, then request and controller.

- [ ] **Step 1: Write tests** (`tests/Feature/Finance/MaintenanceRecordControllerTest.php`)

Cover: Financial can access index; Operator cannot; tenant isolation; Financial can create/update/delete; cannot update other company record. Include a test that the `type` enum rejects invalid values.

- [ ] **Step 2: Create StoreMaintenanceRecordRequest and UpdateMaintenanceRecordRequest**

```php
// rules():
'vehicle_id'   => ['required', 'exists:vehicles,id'],
'type'         => ['required', Rule::in(['preventive', 'corrective', 'emergency', 'routine'])],
'description'  => ['required', 'string', 'max:2000'],
'cost'         => ['required', 'numeric', 'min:0.01'],
'odometer_km'  => ['nullable', 'integer', 'min:0'],
'performed_on' => ['required', 'date'],
'provider'     => ['nullable', 'string', 'max:150'],
```

- [ ] **Step 3: Create MaintenanceRecordController**

Thin CRUD following VehicleController pattern. Use `QueryBuilder` for index with filters: `vehicle_id`, `type`, `date_from`, `date_to`.

- [ ] **Step 4: Run tests and commit**

```bash
php artisan test tests/Feature/Finance/MaintenanceRecordControllerTest.php
php artisan test
git commit -m "feat(finance): add Maintenance Records CRUD"
```

---

## Task 9: Frontend — Expenses, Fuel Records, Maintenance Records

### Expenses/Index.vue

Receives `expenses` (paginated with `expenseCategory`), `categories`, `vehicles`, `filters`.

- Filter bar: category dropdown (shows colored chips), vehicle dropdown, date from/to.
- Table: Data, Categoria (colored chip badge), Valor, Veículo, Frete, Ações.
- Category chip: `<span :style="{ backgroundColor: cat.color }" class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium text-white">`.

### Expenses/Form.vue

Category combobox with create-on-demand:

```javascript
// In data():
categorySearch: '',
categoryMatches: [], // filtered from this.categories
isNewCategory: false,

// In methods:
async selectOrCreateCategory(name) {
    const existing = this.categories.find(c => c.name === name)
    if (existing) {
        this.form.expense_category_id = existing.id
    } else {
        // Create new category
        const response = await axios.post(route('expense-categories.store'), { name })
        this.categories.push(response.data)
        this.form.expense_category_id = response.data.id
    }
}
```

Use a simple custom dropdown (or `<datalist>`) for the combobox — show existing categories as options plus a "Criar: {typed}" option when no match.

### FuelRecords/Index.vue + Form.vue

Index: filters by vehicle, driver, date. Table: Data, Veículo, Motorista, Litros, Preço/L, Total, Posto.
Form: real-time total preview computed from `liters × price_per_liter`.

### Maintenance/Index.vue + Form.vue

Index: filters by vehicle, type, date. Table: Data, Tipo (badge), Veículo, Custo, Odômetro, Fornecedor.
Form: type select with Portuguese labels: `{ preventive: 'Preventiva', corrective: 'Corretiva', emergency: 'Emergência', routine: 'Revisão de rotina' }`.

### AuthenticatedLayout.vue nav additions

```html
<NavLink :href="route('expenses.index')" :active="route().current('expenses.*')">Despesas</NavLink>
<NavLink :href="route('fuel-records.index')" :active="route().current('fuel-records.*')">Abastecimentos</NavLink>
<NavLink :href="route('maintenance-records.index')" :active="route().current('maintenance-records.*')">Manutenções</NavLink>
```

- [ ] **Final step: Build + test**

```bash
npm run build 2>&1 | tail -5
php artisan test
```

- [ ] **Commit frontend**

```bash
git add resources/js/Pages/Finance/ resources/js/Layouts/AuthenticatedLayout.vue
git commit -m "feat(finance): add Expenses, Fuel Records, Maintenance frontend pages"
```

---

## Self-Review Against Spec

| Spec requirement | Covered |
|---|---|
| Expense categories: dynamic tenant-scoped tags with colors | Tasks 2, 3, 5 |
| Seeded defaults on company creation | Task 3 |
| Expenses CRUD with category, optional vehicle/freight, CHECK | Tasks 1, 4, 6 |
| Fuel Records CRUD, total_cost computed and stored | Tasks 1, 2, 7 |
| Maintenance Records CRUD, type enum | Tasks 1, 2, 8 |
| Index filters via spatie/laravel-query-builder | Tasks 0, 6–8 (controllers) |
| Admin+Financial only access (Operator blocked) | Task 4 (policies) |
| RLS on all tables | Task 1 |
| Category combobox with AJAX create-on-demand | Task 9 (frontend) |
