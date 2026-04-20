# Epic 03 — Commercial: Clients & Freight Tables Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Commercial module — Clients CRUD, fixed and per-km freight rate tables — fully scoped to tenants with role-based access control.

**Architecture:** Laravel 11 modular monolith under `app/Modules/Commercial/`. Models use `BelongsToCompany` trait + PostgreSQL RLS. Business logic in Action classes; thin controllers pass to Actions and return Inertia responses. Shallow-nested routes.

**Tech Stack:** Laravel 11, Inertia.js, Vue 3 Options API, `spatie/laravel-data`, `spatie/laravel-permission`, `geekcom/validator-docs` (CPF/CNPJ), PostgreSQL RLS.

**Working directory:** `.worktrees/epic-03-commercial`

**Baseline:** 44 tests passing. Run `php artisan test` to verify at any time.

**Role matrix:**
| Action | Admin | Operator | Financial |
|--------|-------|----------|-----------|
| clients: view | ✓ | ✓ | ✓ |
| clients: create/update | ✓ | ✓ | ✗ |
| clients: delete | ✓ | ✗ | ✗ |
| freight tables + rates: view | ✓ | ✓ | ✓ |
| freight tables + rates: write | ✓ | ✓ | ✗ |

---

## File Map

```
app/Modules/Commercial/
├── Models/
│   ├── Client.php
│   ├── ClientFreightTable.php
│   ├── FixedFreightRate.php
│   └── PerKmFreightRate.php
├── Http/Controllers/
│   ├── ClientController.php
│   ├── ClientFreightTableController.php
│   ├── FixedFreightRateController.php
│   └── PerKmFreightRateController.php
├── Http/Requests/
│   ├── StoreClientRequest.php
│   ├── UpdateClientRequest.php
│   ├── StoreClientFreightTableRequest.php
│   ├── UpdateClientFreightTableRequest.php
│   ├── StoreFixedFreightRateRequest.php
│   ├── UpdateFixedFreightRateRequest.php
│   ├── StorePerKmFreightRateRequest.php
│   └── UpdatePerKmFreightRateRequest.php
├── Actions/
│   ├── CreateClientAction.php
│   ├── UpdateClientAction.php
│   ├── CreateClientFreightTableAction.php
│   ├── UpdateClientFreightTableAction.php
│   ├── CreateFixedFreightRateAction.php
│   ├── UpdateFixedFreightRateAction.php
│   ├── CreatePerKmFreightRateAction.php
│   └── UpdatePerKmFreightRateAction.php
├── Policies/
│   ├── ClientPolicy.php
│   ├── ClientFreightTablePolicy.php
│   ├── FixedFreightRatePolicy.php
│   └── PerKmFreightRatePolicy.php
└── Rules/
    └── ValidBrazilianState.php

database/factories/Commercial/
├── ClientFactory.php
├── ClientFreightTableFactory.php
├── FixedFreightRateFactory.php
└── PerKmFreightRateFactory.php

database/migrations/
├── 2026_04_18_000001_create_clients_table.php
├── 2026_04_18_000002_create_client_freight_tables_table.php
├── 2026_04_18_000003_create_fixed_freight_rates_table.php
├── 2026_04_18_000004_create_per_km_freight_rates_table.php
└── rls/2026_04_18_000005_enable_rls_on_commercial_tables.php

tests/Feature/Commercial/
├── ClientCrudTest.php
├── ClientFreightTableCrudTest.php
├── FixedFreightRateCrudTest.php
└── PerKmFreightRateCrudTest.php

resources/js/Pages/Commercial/
├── Clients/Index.vue
├── Clients/Create.vue
├── Clients/Edit.vue
├── FreightTables/Create.vue
├── FreightTables/Edit.vue
├── FreightTables/Show.vue
├── FixedRates/Create.vue
├── FixedRates/Edit.vue
├── PerKmRates/Create.vue
└── PerKmRates/Edit.vue
```

---

## Task 1: Migrations

**Files:**
- Create: `database/migrations/2026_04_18_000001_create_clients_table.php`
- Create: `database/migrations/2026_04_18_000002_create_client_freight_tables_table.php`
- Create: `database/migrations/2026_04_18_000003_create_fixed_freight_rates_table.php`
- Create: `database/migrations/2026_04_18_000004_create_per_km_freight_rates_table.php`
- Create: `database/migrations/rls/2026_04_18_000005_enable_rls_on_commercial_tables.php`

- [ ] **Step 1: Create clients migration**

```php
// database/migrations/2026_04_18_000001_create_clients_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('name');
            $table->string('document', 14);
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address_street')->nullable();
            $table->string('address_number', 20)->nullable();
            $table->string('address_complement')->nullable();
            $table->string('address_neighborhood')->nullable();
            $table->string('address_city')->nullable();
            $table->char('address_state', 2)->nullable();
            $table->char('address_zip', 8)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'document']);
            $table->index(['company_id', 'active']);
        });
    }

    public function down(): void { Schema::dropIfExists('clients'); }
};
```

- [ ] **Step 2: Create client_freight_tables migration**

```php
// database/migrations/2026_04_18_000002_create_client_freight_tables_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_freight_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name');
            $table->string('pricing_model');   // 'fixed' | 'per_km'
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'client_id', 'name']);
        });
    }

    public function down(): void { Schema::dropIfExists('client_freight_tables'); }
};
```

- [ ] **Step 3: Create fixed_freight_rates migration**

```php
// database/migrations/2026_04_18_000003_create_fixed_freight_rates_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fixed_freight_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('client_freight_table_id')
                  ->constrained('client_freight_tables')
                  ->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->decimal('avg_km', 10, 2)->nullable();
            $table->decimal('tolls', 12, 2)->nullable();
            $table->decimal('fuel_cost', 12, 2)->nullable();
            $table->timestamps();
            $table->unique(['client_freight_table_id', 'name']);
        });
    }

    public function down(): void { Schema::dropIfExists('fixed_freight_rates'); }
};
```

- [ ] **Step 4: Create per_km_freight_rates migration**

```php
// database/migrations/2026_04_18_000004_create_per_km_freight_rates_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('per_km_freight_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->char('state', 2);
            $table->decimal('rate_per_km', 10, 4);
            $table->timestamps();
            $table->unique(['company_id', 'client_id', 'state']);
        });
    }

    public function down(): void { Schema::dropIfExists('per_km_freight_rates'); }
};
```

- [ ] **Step 5: Create RLS migration for commercial tables**

```php
// database/migrations/rls/2026_04_18_000005_enable_rls_on_commercial_tables.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private array $tables = [
        'clients',
        'client_freight_tables',
        'fixed_freight_rates',
        'per_km_freight_rates',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY {$table}_tenant_isolation ON {$table}
                USING (
                    current_setting('app.current_company_id', true) IS NULL
                    OR current_setting('app.current_company_id', true) = ''
                    OR company_id = current_setting('app.current_company_id', true)::bigint
                )
                WITH CHECK (
                    current_setting('app.current_company_id', true) IS NULL
                    OR current_setting('app.current_company_id', true) = ''
                    OR company_id = current_setting('app.current_company_id', true)::bigint
                )
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
```

- [ ] **Step 6: Run migrations and verify RLS coverage**

```bash
php artisan migrate
php artisan test --filter RlsCoverageTest
```

Expected: `Tests: 1 passed`

- [ ] **Step 7: Commit**

```bash
git add database/migrations/
git commit -m "feat(commercial): add migrations for clients, freight tables, and rates"
```

---

## Task 2: Models and Factories

**Files:** `app/Modules/Commercial/Models/*.php`, `database/factories/Commercial/*.php`

- [ ] **Step 1: Create Client model**

```php
// app/Modules/Commercial/Models/Client.php
<?php
namespace App\Modules\Commercial\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'document', 'email', 'phone',
        'address_street', 'address_number', 'address_complement',
        'address_neighborhood', 'address_city', 'address_state',
        'address_zip', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function getDocumentTypeAttribute(): string
    {
        return strlen($this->document) === 11 ? 'cpf' : 'cnpj';
    }

    public function freightTables(): HasMany
    {
        return $this->hasMany(ClientFreightTable::class);
    }

    public function perKmRates(): HasMany
    {
        return $this->hasMany(PerKmFreightRate::class);
    }
}
```

- [ ] **Step 2: Create ClientFreightTable model**

```php
// app/Modules/Commercial/Models/ClientFreightTable.php
<?php
namespace App\Modules\Commercial\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientFreightTable extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'client_id', 'name', 'pricing_model', 'active'];
    protected $casts = ['active' => 'boolean'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function fixedRates(): HasMany
    {
        return $this->hasMany(FixedFreightRate::class);
    }
}
```

- [ ] **Step 3: Create FixedFreightRate model**

```php
// app/Modules/Commercial/Models/FixedFreightRate.php
<?php
namespace App\Modules\Commercial\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedFreightRate extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'client_freight_table_id', 'name',
        'price', 'avg_km', 'tolls', 'fuel_cost',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'avg_km' => 'decimal:2',
        'tolls' => 'decimal:2',
        'fuel_cost' => 'decimal:2',
    ];

    public function freightTable(): BelongsTo
    {
        return $this->belongsTo(ClientFreightTable::class, 'client_freight_table_id');
    }
}
```

- [ ] **Step 4: Create PerKmFreightRate model**

```php
// app/Modules/Commercial/Models/PerKmFreightRate.php
<?php
namespace App\Modules\Commercial\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerKmFreightRate extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'client_id', 'state', 'rate_per_km'];
    protected $casts = ['rate_per_km' => 'decimal:4'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
```

- [ ] **Step 5: Create factories**

```php
// database/factories/Commercial/ClientFactory.php
<?php
namespace Database\Factories\Commercial;

use App\Modules\Commercial\Models\Client;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        // Valid CPF digits (simplified — use seeded known-valid values)
        return [
            'company_id' => Company::factory(),
            'name' => fake()->company(),
            'document' => '11144477735',   // known-valid CPF
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('119########'),
            'active' => true,
        ];
    }

    public function cnpj(): static
    {
        return $this->state(['document' => '11222333000181']); // known-valid CNPJ
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
```

```php
// database/factories/Commercial/ClientFreightTableFactory.php
<?php
namespace Database\Factories\Commercial;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\ClientFreightTable;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFreightTableFactory extends Factory
{
    protected $model = ClientFreightTable::class;

    public function definition(): array
    {
        $client = Client::factory()->create();
        return [
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'name' => fake()->words(3, true),
            'pricing_model' => 'fixed',
            'active' => true,
        ];
    }

    public function perKm(): static
    {
        return $this->state(['pricing_model' => 'per_km']);
    }
}
```

```php
// database/factories/Commercial/FixedFreightRateFactory.php
<?php
namespace Database\Factories\Commercial;

use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\FixedFreightRate;
use Illuminate\Database\Eloquent\Factories\Factory;

class FixedFreightRateFactory extends Factory
{
    protected $model = FixedFreightRate::class;

    public function definition(): array
    {
        $table = ClientFreightTable::factory()->create();
        return [
            'company_id' => $table->company_id,
            'client_freight_table_id' => $table->id,
            'name' => fake()->city().' '.fake()->randomNumber(1),
            'price' => fake()->randomFloat(2, 100, 5000),
            'avg_km' => null,
            'tolls' => null,
            'fuel_cost' => null,
        ];
    }
}
```

```php
// database/factories/Commercial/PerKmFreightRateFactory.php
<?php
namespace Database\Factories\Commercial;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\PerKmFreightRate;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerKmFreightRateFactory extends Factory
{
    protected $model = PerKmFreightRate::class;

    private static array $states = ['SP','RJ','MG','RS','PR','SC','BA','GO'];
    private static int $stateIndex = 0;

    public function definition(): array
    {
        $client = Client::factory()->create();
        return [
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'state' => self::$states[self::$stateIndex++ % count(self::$states)],
            'rate_per_km' => fake()->randomFloat(4, 1, 10),
        ];
    }
}
```

- [ ] **Step 6: Register factory namespace in composer.json**

In `composer.json`, the `autoload` section already covers `Database\\Factories\\` pointing to `database/factories/`. Since factories are in a subdirectory, verify discovery works by adding the subdirectory to PSR-4 autoload if needed. If factories use `protected $model`, Laravel auto-discovers them. Run:

```bash
composer dump-autoload
php artisan test --filter RlsCoverageTest
```

Expected: `Tests: 1 passed`

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Commercial/Models/ database/factories/Commercial/
git commit -m "feat(commercial): add models and factories"
```

---

## Task 3: Policies and Permissions

**Files:** `app/Modules/Commercial/Policies/*.php`, `app/Modules/Identity/Actions/SeedCompanyRolesAction.php`, `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Add permissions to SeedCompanyRolesAction**

```php
// app/Modules/Identity/Actions/SeedCompanyRolesAction.php
<?php
namespace App\Modules\Identity\Actions;

use App\Modules\Tenancy\Models\Company;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SeedCompanyRolesAction
{
    public function handle(Company $company): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        foreach (['Admin', 'Operator', 'Financial'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $permissions = [
            'clients.view',
            'clients.manage',
            'clients.delete',
            'freight_tables.view',
            'freight_tables.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $admin    = Role::findByName('Admin', 'web');
        $operator = Role::findByName('Operator', 'web');
        $financial = Role::findByName('Financial', 'web');

        $admin->givePermissionTo($permissions);
        $operator->givePermissionTo(['clients.view','clients.manage','freight_tables.view','freight_tables.manage']);
        $financial->givePermissionTo(['clients.view','freight_tables.view']);
    }
}
```

- [ ] **Step 2: Create ClientPolicy**

```php
// app/Modules/Commercial/Policies/ClientPolicy.php
<?php
namespace App\Modules\Commercial\Policies;

use App\Models\User;
use App\Modules\Commercial\Models\Client;
use App\Modules\Tenancy\Policies\TenantPolicy;

class ClientPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clients.view');
    }

    public function view(User $user, Client $client): bool
    {
        return $user->can('clients.view') && $this->belongsToTenant($user, $client);
    }

    public function create(User $user): bool
    {
        return $user->can('clients.manage');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can('clients.manage') && $this->belongsToTenant($user, $client);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->can('clients.delete') && $this->belongsToTenant($user, $client);
    }
}
```

- [ ] **Step 3: Create ClientFreightTablePolicy**

```php
// app/Modules/Commercial/Policies/ClientFreightTablePolicy.php
<?php
namespace App\Modules\Commercial\Policies;

use App\Models\User;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Tenancy\Policies\TenantPolicy;

class ClientFreightTablePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool   { return $user->can('freight_tables.view'); }
    public function view(User $user, ClientFreightTable $t): bool
    {
        return $user->can('freight_tables.view') && $this->belongsToTenant($user, $t);
    }
    public function create(User $user): bool    { return $user->can('freight_tables.manage'); }
    public function update(User $user, ClientFreightTable $t): bool
    {
        return $user->can('freight_tables.manage') && $this->belongsToTenant($user, $t);
    }
    public function delete(User $user, ClientFreightTable $t): bool
    {
        return $user->can('freight_tables.manage') && $this->belongsToTenant($user, $t);
    }
}
```

- [ ] **Step 4: Create FixedFreightRatePolicy and PerKmFreightRatePolicy**

```php
// app/Modules/Commercial/Policies/FixedFreightRatePolicy.php
<?php
namespace App\Modules\Commercial\Policies;

use App\Models\User;
use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Tenancy\Policies\TenantPolicy;

class FixedFreightRatePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool  { return $user->can('freight_tables.view'); }
    public function create(User $user): bool   { return $user->can('freight_tables.manage'); }
    public function update(User $user, FixedFreightRate $r): bool
    {
        return $user->can('freight_tables.manage') && $this->belongsToTenant($user, $r);
    }
    public function delete(User $user, FixedFreightRate $r): bool
    {
        return $user->can('freight_tables.manage') && $this->belongsToTenant($user, $r);
    }
}
```

```php
// app/Modules/Commercial/Policies/PerKmFreightRatePolicy.php
<?php
namespace App\Modules\Commercial\Policies;

use App\Models\User;
use App\Modules\Commercial\Models\PerKmFreightRate;
use App\Modules\Tenancy\Policies\TenantPolicy;

class PerKmFreightRatePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool  { return $user->can('freight_tables.view'); }
    public function create(User $user): bool   { return $user->can('freight_tables.manage'); }
    public function update(User $user, PerKmFreightRate $r): bool
    {
        return $user->can('freight_tables.manage') && $this->belongsToTenant($user, $r);
    }
    public function delete(User $user, PerKmFreightRate $r): bool
    {
        return $user->can('freight_tables.manage') && $this->belongsToTenant($user, $r);
    }
}
```

- [ ] **Step 5: Register policies in AppServiceProvider**

```php
// app/Providers/AppServiceProvider.php  — add inside boot()
use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Commercial\Models\PerKmFreightRate;
use App\Modules\Commercial\Policies\ClientPolicy;
use App\Modules\Commercial\Policies\ClientFreightTablePolicy;
use App\Modules\Commercial\Policies\FixedFreightRatePolicy;
use App\Modules\Commercial\Policies\PerKmFreightRatePolicy;
use Illuminate\Support\Facades\Gate;

// Inside boot():
Gate::policy(Client::class, ClientPolicy::class);
Gate::policy(ClientFreightTable::class, ClientFreightTablePolicy::class);
Gate::policy(FixedFreightRate::class, FixedFreightRatePolicy::class);
Gate::policy(PerKmFreightRate::class, PerKmFreightRatePolicy::class);
```

- [ ] **Step 6: Run tests**

```bash
php artisan test
```

Expected: 44 passed (existing suite unbroken)

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Commercial/Policies/ app/Modules/Identity/Actions/SeedCompanyRolesAction.php app/Providers/AppServiceProvider.php
git commit -m "feat(commercial): add policies and permissions"
```

---

## Task 4: ValidBrazilianState Rule + Routes + Controllers skeleton

**Files:** `app/Modules/Commercial/Rules/ValidBrazilianState.php`, `routes/web.php`, 4 controller files

- [ ] **Step 1: Create ValidBrazilianState rule**

```php
// app/Modules/Commercial/Rules/ValidBrazilianState.php
<?php
namespace App\Modules\Commercial\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidBrazilianState implements ValidationRule
{
    private const STATES = [
        'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA',
        'MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN',
        'RS','RO','RR','SC','SP','SE','TO',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! in_array(strtoupper($value), self::STATES, true)) {
            $fail('The :attribute must be a valid Brazilian state code (UF).');
        }
    }
}
```

- [ ] **Step 2: Add Commercial routes to web.php**

Append to the existing `Route::middleware(['auth', 'tenant'])` group in `routes/web.php`:

```php
use App\Modules\Commercial\Http\Controllers\ClientController;
use App\Modules\Commercial\Http\Controllers\ClientFreightTableController;
use App\Modules\Commercial\Http\Controllers\FixedFreightRateController;
use App\Modules\Commercial\Http\Controllers\PerKmFreightRateController;

// Inside Route::middleware(['auth', 'tenant'])->group():
Route::resource('clients', ClientController::class);
Route::resource('clients.freight-tables', ClientFreightTableController::class)
     ->shallow();
Route::resource('freight-tables.fixed-rates', FixedFreightRateController::class)
     ->shallow();
Route::resource('clients.per-km-rates', PerKmFreightRateController::class)
     ->shallow();
```

- [ ] **Step 3: Create ClientController skeleton**

```php
// app/Modules/Commercial/Http/Controllers/ClientController.php
<?php
namespace App\Modules\Commercial\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commercial\Actions\CreateClientAction;
use App\Modules\Commercial\Actions\UpdateClientAction;
use App\Modules\Commercial\Http\Requests\StoreClientRequest;
use App\Modules\Commercial\Http\Requests\UpdateClientRequest;
use App\Modules\Commercial\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Client::class);

        $clients = Client::query()
            ->when(request('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('document', 'like', "%{$s}%"))
            ->when(request()->has('active'), fn ($q) => $q->where('active', request()->boolean('active')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Commercial/Clients/Index', [
            'clients' => $clients,
            'filters' => request()->only('search', 'active'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Client::class);
        return Inertia::render('Commercial/Clients/Create');
    }

    public function store(StoreClientRequest $request, CreateClientAction $action): RedirectResponse
    {
        $action->handle($request->validated());
        return redirect()->route('clients.index')->with('success', 'Client created.');
    }

    public function edit(Client $client): Response
    {
        $this->authorize('update', $client);
        return Inertia::render('Commercial/Clients/Edit', ['client' => $client]);
    }

    public function update(UpdateClientRequest $request, Client $client, UpdateClientAction $action): RedirectResponse
    {
        $action->handle($client, $request->validated());
        return redirect()->route('clients.index')->with('success', 'Client updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);
        $client->delete();
        return redirect()->route('clients.index')->with('success', 'Client deleted.');
    }
}
```

- [ ] **Step 4: Create remaining controller skeletons**

```php
// app/Modules/Commercial/Http/Controllers/ClientFreightTableController.php
<?php
namespace App\Modules\Commercial\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commercial\Actions\CreateClientFreightTableAction;
use App\Modules\Commercial\Actions\UpdateClientFreightTableAction;
use App\Modules\Commercial\Http\Requests\StoreClientFreightTableRequest;
use App\Modules\Commercial\Http\Requests\UpdateClientFreightTableRequest;
use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\ClientFreightTable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientFreightTableController extends Controller
{
    public function create(Client $client): Response
    {
        $this->authorize('create', ClientFreightTable::class);
        return Inertia::render('Commercial/FreightTables/Create', ['client' => $client]);
    }

    public function store(StoreClientFreightTableRequest $request, Client $client, CreateClientFreightTableAction $action): RedirectResponse
    {
        $action->handle($client, $request->validated());
        return redirect()->route('clients.show', $client)->with('success', 'Freight table created.');
    }

    public function show(ClientFreightTable $freightTable): Response
    {
        $this->authorize('view', $freightTable);
        $freightTable->load(['client', 'fixedRates']);
        return Inertia::render('Commercial/FreightTables/Show', ['freightTable' => $freightTable]);
    }

    public function edit(ClientFreightTable $freightTable): Response
    {
        $this->authorize('update', $freightTable);
        return Inertia::render('Commercial/FreightTables/Edit', ['freightTable' => $freightTable]);
    }

    public function update(UpdateClientFreightTableRequest $request, ClientFreightTable $freightTable, UpdateClientFreightTableAction $action): RedirectResponse
    {
        $action->handle($freightTable, $request->validated());
        return redirect()->route('freight-tables.show', $freightTable)->with('success', 'Updated.');
    }

    public function destroy(ClientFreightTable $freightTable): RedirectResponse
    {
        $this->authorize('delete', $freightTable);
        $freightTable->delete();
        return redirect()->route('clients.show', $freightTable->client_id)->with('success', 'Deleted.');
    }
}
```

```php
// app/Modules/Commercial/Http/Controllers/FixedFreightRateController.php
<?php
namespace App\Modules\Commercial\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commercial\Actions\CreateFixedFreightRateAction;
use App\Modules\Commercial\Actions\UpdateFixedFreightRateAction;
use App\Modules\Commercial\Http\Requests\StoreFixedFreightRateRequest;
use App\Modules\Commercial\Http\Requests\UpdateFixedFreightRateRequest;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\FixedFreightRate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FixedFreightRateController extends Controller
{
    public function create(ClientFreightTable $freightTable): Response
    {
        $this->authorize('create', FixedFreightRate::class);
        return Inertia::render('Commercial/FixedRates/Create', ['freightTable' => $freightTable]);
    }

    public function store(StoreFixedFreightRateRequest $request, ClientFreightTable $freightTable, CreateFixedFreightRateAction $action): RedirectResponse
    {
        $action->handle($freightTable, $request->validated());
        return redirect()->route('freight-tables.show', $freightTable)->with('success', 'Rate created.');
    }

    public function edit(FixedFreightRate $fixedRate): Response
    {
        $this->authorize('update', $fixedRate);
        return Inertia::render('Commercial/FixedRates/Edit', ['rate' => $fixedRate->load('freightTable')]);
    }

    public function update(UpdateFixedFreightRateRequest $request, FixedFreightRate $fixedRate, UpdateFixedFreightRateAction $action): RedirectResponse
    {
        $action->handle($fixedRate, $request->validated());
        return redirect()->route('freight-tables.show', $fixedRate->client_freight_table_id)->with('success', 'Rate updated.');
    }

    public function destroy(FixedFreightRate $fixedRate): RedirectResponse
    {
        $this->authorize('delete', $fixedRate);
        $fixedRate->delete();
        return redirect()->route('freight-tables.show', $fixedRate->client_freight_table_id)->with('success', 'Deleted.');
    }
}
```

```php
// app/Modules/Commercial/Http/Controllers/PerKmFreightRateController.php
<?php
namespace App\Modules\Commercial\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commercial\Actions\CreatePerKmFreightRateAction;
use App\Modules\Commercial\Actions\UpdatePerKmFreightRateAction;
use App\Modules\Commercial\Http\Requests\StorePerKmFreightRateRequest;
use App\Modules\Commercial\Http\Requests\UpdatePerKmFreightRateRequest;
use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\PerKmFreightRate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PerKmFreightRateController extends Controller
{
    public function create(Client $client): Response
    {
        $this->authorize('create', PerKmFreightRate::class);
        return Inertia::render('Commercial/PerKmRates/Create', ['client' => $client]);
    }

    public function store(StorePerKmFreightRateRequest $request, Client $client, CreatePerKmFreightRateAction $action): RedirectResponse
    {
        $action->handle($client, $request->validated());
        return redirect()->route('clients.show', $client)->with('success', 'Rate created.');
    }

    public function edit(PerKmFreightRate $perKmRate): Response
    {
        $this->authorize('update', $perKmRate);
        return Inertia::render('Commercial/PerKmRates/Edit', ['rate' => $perKmRate->load('client')]);
    }

    public function update(UpdatePerKmFreightRateRequest $request, PerKmFreightRate $perKmRate, UpdatePerKmFreightRateAction $action): RedirectResponse
    {
        $action->handle($perKmRate, $request->validated());
        return redirect()->route('clients.show', $perKmRate->client_id)->with('success', 'Rate updated.');
    }

    public function destroy(PerKmFreightRate $perKmRate): RedirectResponse
    {
        $this->authorize('delete', $perKmRate);
        $perKmRate->delete();
        return redirect()->route('clients.show', $perKmRate->client_id)->with('success', 'Deleted.');
    }
}
```

- [ ] **Step 5: Commit skeleton**

```bash
git add app/Modules/Commercial/ routes/web.php
git commit -m "feat(commercial): add rule, routes, and controller skeletons"
```

---

## Task 5: Form Requests and Actions

**Files:** 8 request files, 8 action files

- [ ] **Step 1: Create StoreClientRequest**

```php
// app/Modules/Commercial/Http/Requests/StoreClientRequest.php
<?php
namespace App\Modules\Commercial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:255'],
            'document'           => [
                'required', 'string',
                'cpf_ou_cnpj',
                Rule::unique('clients', 'document')
                    ->where('company_id', auth()->user()->company_id),
            ],
            'email'              => ['nullable', 'email', 'max:255'],
            'phone'              => ['nullable', 'string', 'max:20'],
            'address_street'     => ['nullable', 'string', 'max:255'],
            'address_number'     => ['nullable', 'string', 'max:20'],
            'address_complement' => ['nullable', 'string', 'max:255'],
            'address_neighborhood'=> ['nullable', 'string', 'max:255'],
            'address_city'       => ['nullable', 'string', 'max:255'],
            'address_state'      => ['nullable', 'string', 'size:2'],
            'address_zip'        => ['nullable', 'string', 'size:8'],
            'active'             => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->document) {
            $this->merge(['document' => preg_replace('/\D/', '', $this->document)]);
        }
    }
}
```

- [ ] **Step 2: Create UpdateClientRequest**

```php
// app/Modules/Commercial/Http/Requests/UpdateClientRequest.php
<?php
namespace App\Modules\Commercial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:255'],
            'document'           => [
                'required', 'string',
                'cpf_ou_cnpj',
                Rule::unique('clients', 'document')
                    ->where('company_id', auth()->user()->company_id)
                    ->ignore($this->route('client')),
            ],
            'email'              => ['nullable', 'email', 'max:255'],
            'phone'              => ['nullable', 'string', 'max:20'],
            'address_street'     => ['nullable', 'string', 'max:255'],
            'address_number'     => ['nullable', 'string', 'max:20'],
            'address_complement' => ['nullable', 'string', 'max:255'],
            'address_neighborhood'=> ['nullable', 'string', 'max:255'],
            'address_city'       => ['nullable', 'string', 'max:255'],
            'address_state'      => ['nullable', 'string', 'size:2'],
            'address_zip'        => ['nullable', 'string', 'size:8'],
            'active'             => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->document) {
            $this->merge(['document' => preg_replace('/\D/', '', $this->document)]);
        }
    }
}
```

- [ ] **Step 3: Create freight table requests**

```php
// app/Modules/Commercial/Http/Requests/StoreClientFreightTableRequest.php
<?php
namespace App\Modules\Commercial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientFreightTableRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'          => [
                'required', 'string', 'max:255',
                Rule::unique('client_freight_tables', 'name')
                    ->where('client_id', $this->route('client')->id),
            ],
            'pricing_model' => ['required', Rule::in(['fixed', 'per_km'])],
            'active'        => ['boolean'],
        ];
    }
}
```

```php
// app/Modules/Commercial/Http/Requests/UpdateClientFreightTableRequest.php
<?php
namespace App\Modules\Commercial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientFreightTableRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $table = $this->route('freight_table');
        return [
            'name'   => [
                'required', 'string', 'max:255',
                Rule::unique('client_freight_tables', 'name')
                    ->where('client_id', $table->client_id)
                    ->ignore($table),
            ],
            'active' => ['boolean'],
            // pricing_model intentionally excluded — immutable after creation
        ];
    }
}
```

- [ ] **Step 4: Create rate requests**

```php
// app/Modules/Commercial/Http/Requests/StoreFixedFreightRateRequest.php
<?php
namespace App\Modules\Commercial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFixedFreightRateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'      => [
                'required', 'string', 'max:255',
                Rule::unique('fixed_freight_rates', 'name')
                    ->where('client_freight_table_id', $this->route('freight_table')->id),
            ],
            'price'     => ['required', 'numeric', 'min:0'],
            'avg_km'    => ['nullable', 'numeric', 'min:0'],
            'tolls'     => ['nullable', 'numeric', 'min:0'],
            'fuel_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

```php
// app/Modules/Commercial/Http/Requests/UpdateFixedFreightRateRequest.php
<?php
namespace App\Modules\Commercial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFixedFreightRateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $rate = $this->route('fixed_rate');
        return [
            'name'      => [
                'required', 'string', 'max:255',
                Rule::unique('fixed_freight_rates', 'name')
                    ->where('client_freight_table_id', $rate->client_freight_table_id)
                    ->ignore($rate),
            ],
            'price'     => ['required', 'numeric', 'min:0'],
            'avg_km'    => ['nullable', 'numeric', 'min:0'],
            'tolls'     => ['nullable', 'numeric', 'min:0'],
            'fuel_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

```php
// app/Modules/Commercial/Http/Requests/StorePerKmFreightRateRequest.php
<?php
namespace App\Modules\Commercial\Http\Requests;

use App\Modules\Commercial\Rules\ValidBrazilianState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePerKmFreightRateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'state'       => [
                'required', 'string', 'size:2', new ValidBrazilianState,
                Rule::unique('per_km_freight_rates', 'state')
                    ->where('company_id', auth()->user()->company_id)
                    ->where('client_id', $this->route('client')->id),
            ],
            'rate_per_km' => ['required', 'numeric', 'min:0'],
        ];
    }
}
```

```php
// app/Modules/Commercial/Http/Requests/UpdatePerKmFreightRateRequest.php
<?php
namespace App\Modules\Commercial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerKmFreightRateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'rate_per_km' => ['required', 'numeric', 'min:0'],
            // state intentionally excluded — immutable (it's the unique key)
        ];
    }
}
```

- [ ] **Step 5: Create Actions**

```php
// app/Modules/Commercial/Actions/CreateClientAction.php
<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\Client;

class CreateClientAction
{
    public function handle(array $data): Client
    {
        return Client::create($data);
    }
}
```

```php
// app/Modules/Commercial/Actions/UpdateClientAction.php
<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\Client;

class UpdateClientAction
{
    public function handle(Client $client, array $data): Client
    {
        $client->update($data);
        return $client;
    }
}
```

```php
// app/Modules/Commercial/Actions/CreateClientFreightTableAction.php
<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\ClientFreightTable;

class CreateClientFreightTableAction
{
    public function handle(Client $client, array $data): ClientFreightTable
    {
        return $client->freightTables()->create($data);
    }
}
```

```php
// app/Modules/Commercial/Actions/UpdateClientFreightTableAction.php
<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\ClientFreightTable;

class UpdateClientFreightTableAction
{
    public function handle(ClientFreightTable $table, array $data): ClientFreightTable
    {
        $table->update($data);
        return $table;
    }
}
```

```php
// app/Modules/Commercial/Actions/CreateFixedFreightRateAction.php
<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\FixedFreightRate;

class CreateFixedFreightRateAction
{
    public function handle(ClientFreightTable $table, array $data): FixedFreightRate
    {
        return $table->fixedRates()->create(
            array_merge($data, ['company_id' => $table->company_id])
        );
    }
}
```

```php
// app/Modules/Commercial/Actions/UpdateFixedFreightRateAction.php
<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\FixedFreightRate;

class UpdateFixedFreightRateAction
{
    public function handle(FixedFreightRate $rate, array $data): FixedFreightRate
    {
        $rate->update($data);
        return $rate;
    }
}
```

```php
// app/Modules/Commercial/Actions/CreatePerKmFreightRateAction.php
<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\PerKmFreightRate;

class CreatePerKmFreightRateAction
{
    public function handle(Client $client, array $data): PerKmFreightRate
    {
        return $client->perKmRates()->create($data);
    }
}
```

```php
// app/Modules/Commercial/Actions/UpdatePerKmFreightRateAction.php
<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\PerKmFreightRate;

class UpdatePerKmFreightRateAction
{
    public function handle(PerKmFreightRate $rate, array $data): PerKmFreightRate
    {
        $rate->update($data);
        return $rate;
    }
}
```

- [ ] **Step 6: Run tests**

```bash
php artisan test
```

Expected: 44 passed

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Commercial/Http/ app/Modules/Commercial/Actions/
git commit -m "feat(commercial): add form requests and actions"
```

---

## Task 6: Client CRUD Feature Tests

**Files:** `tests/Feature/Commercial/ClientCrudTest.php`

- [ ] **Step 1: Create test helper trait for Commercial**

```php
// tests/Feature/Commercial/ClientCrudTest.php
<?php
namespace Tests\Feature\Commercial;

use App\Models\User;
use App\Modules\Commercial\Models\Client;
use App\Modules\Identity\Actions\SeedCompanyRolesAction;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

class ClientCrudTest extends TenantTestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, ?Company $company = null): User
    {
        $company ??= Company::factory()->create();
        app(SeedCompanyRolesAction::class)->handle($company);
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);
        return $user;
    }

    // --- viewAny ---

    public function test_admin_can_list_clients(): void
    {
        $user = $this->makeUser('Admin');
        Client::factory()->create(['company_id' => $user->company_id, 'document' => '11144477735']);

        $this->actingAsTenant($user)->get(route('clients.index'))->assertOk();
    }

    public function test_financial_can_list_clients(): void
    {
        $user = $this->makeUser('Financial');
        $this->actingAsTenant($user)->get(route('clients.index'))->assertOk();
    }

    // --- tenant isolation ---

    public function test_client_from_other_tenant_returns_404(): void
    {
        $userA = $this->makeUser('Admin');
        $client = Client::factory()->create(['company_id' => Company::factory()->create()->id, 'document' => '11144477735']);

        $this->actingAsTenant($userA)
            ->get(route('clients.edit', $client))
            ->assertNotFound();
    }

    // --- create ---

    public function test_admin_can_create_client(): void
    {
        $user = $this->makeUser('Admin');

        $this->actingAsTenant($user)
            ->post(route('clients.store'), [
                'name' => 'Transportes Silva',
                'document' => '111.444.777-35',  // masked CPF
                'active' => true,
            ])
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', [
            'company_id' => $user->company_id,
            'document' => '11144477735',  // stored without mask
        ]);
    }

    public function test_operator_can_create_client(): void
    {
        $user = $this->makeUser('Operator');

        $this->actingAsTenant($user)
            ->post(route('clients.store'), [
                'name' => 'Cliente Teste',
                'document' => '11144477735',
                'active' => true,
            ])
            ->assertRedirect();
    }

    public function test_financial_cannot_create_client(): void
    {
        $user = $this->makeUser('Financial');

        $this->actingAsTenant($user)
            ->post(route('clients.store'), ['name' => 'X', 'document' => '11144477735'])
            ->assertForbidden();
    }

    // --- validation ---

    public function test_invalid_cpf_is_rejected(): void
    {
        $user = $this->makeUser('Admin');

        $this->actingAsTenant($user)
            ->post(route('clients.store'), ['name' => 'X', 'document' => '11111111111'])
            ->assertSessionHasErrors('document');
    }

    public function test_document_must_be_unique_per_tenant(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeUser('Admin', $company);
        Client::factory()->create(['company_id' => $company->id, 'document' => '11144477735']);

        $this->actingAsTenant($user)
            ->post(route('clients.store'), ['name' => 'Duplicate', 'document' => '11144477735'])
            ->assertSessionHasErrors('document');
    }

    public function test_same_document_allowed_across_tenants(): void
    {
        $this->makeUser('Admin', Company::factory()->create());  // seeds first tenant
        Client::factory()->create(['document' => '11144477735']);  // first tenant

        $user2 = $this->makeUser('Admin');
        $this->actingAsTenant($user2)
            ->post(route('clients.store'), ['name' => 'Other', 'document' => '11144477735', 'active' => true])
            ->assertRedirect();
    }

    // --- update ---

    public function test_operator_can_update_client(): void
    {
        $user = $this->makeUser('Operator');
        $client = Client::factory()->create(['company_id' => $user->company_id, 'document' => '11144477735']);

        $this->actingAsTenant($user)
            ->put(route('clients.update', $client), [
                'name' => 'Updated Name',
                'document' => '11144477735',
                'active' => true,
            ])
            ->assertRedirect(route('clients.index'));
    }

    // --- delete ---

    public function test_admin_can_delete_client(): void
    {
        $user = $this->makeUser('Admin');
        $client = Client::factory()->create(['company_id' => $user->company_id, 'document' => '11144477735']);

        $this->actingAsTenant($user)
            ->delete(route('clients.destroy', $client))
            ->assertRedirect(route('clients.index'));

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_operator_cannot_delete_client(): void
    {
        $user = $this->makeUser('Operator');
        $client = Client::factory()->create(['company_id' => $user->company_id, 'document' => '11144477735']);

        $this->actingAsTenant($user)
            ->delete(route('clients.destroy', $client))
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run tests — expect failures (controllers/actions don't exist yet)**

```bash
php artisan test tests/Feature/Commercial/ClientCrudTest.php
```

Expected: multiple failures (route not found, 500s)

- [ ] **Step 3: Run full suite — verify nothing is broken**

```bash
php artisan test
```

- [ ] **Step 4: Fix any issues surfaced, then commit test file**

```bash
git add tests/Feature/Commercial/ClientCrudTest.php
git commit -m "test(commercial): add client CRUD feature tests"
```

---

## Task 7: Make Client Tests Pass

- [ ] **Step 1: Run client tests**

```bash
php artisan test tests/Feature/Commercial/ClientCrudTest.php
```

Debug any failures. Common issues:
- Route model binding — ensure `Client` model's factory `document` field doesn't collide.
- `cpf_ou_cnpj` rule not found — `geekcom/validator-docs` must be registered. Verify its ServiceProvider is auto-discovered in `vendor/geekcom/validator-docs/src/`.
- `BelongsToCompany` not filling `company_id` — ensure `actingAsTenant` is called before factory creates client.

- [ ] **Step 2: If `cpf_ou_cnpj` rule is missing, verify package registration**

```bash
php artisan tinker --execute="validator(['doc'=>'11144477735'],['doc'=>'cpf_ou_cnpj'])->passes()"
```

Expected: `= true`

- [ ] **Step 3: Run full suite**

```bash
php artisan test
```

Expected: all passing (44 + client tests)

- [ ] **Step 4: Commit**

```bash
git add .
git commit -m "feat(commercial): implement client CRUD — tests passing"
```

---

## Task 8: Freight Table, Fixed Rate, and Per-km Rate Tests + Implementation

**Files:** `tests/Feature/Commercial/ClientFreightTableCrudTest.php`, `tests/Feature/Commercial/FixedFreightRateCrudTest.php`, `tests/Feature/Commercial/PerKmFreightRateCrudTest.php`

- [ ] **Step 1: Create ClientFreightTableCrudTest**

```php
// tests/Feature/Commercial/ClientFreightTableCrudTest.php
<?php
namespace Tests\Feature\Commercial;

use App\Models\User;
use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Identity\Actions\SeedCompanyRolesAction;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

class ClientFreightTableCrudTest extends TenantTestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, ?Company $company = null): User
    {
        $company ??= Company::factory()->create();
        app(SeedCompanyRolesAction::class)->handle($company);
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);
        return $user;
    }

    private function makeClient(User $user): Client
    {
        return Client::factory()->create([
            'company_id' => $user->company_id,
            'document' => '11144477735',
        ]);
    }

    public function test_operator_can_create_freight_table(): void
    {
        $user = $this->makeUser('Operator');
        $client = $this->makeClient($user);

        $this->actingAsTenant($user)
            ->post(route('clients.freight-tables.store', $client), [
                'name' => 'Tabela SP',
                'pricing_model' => 'fixed',
                'active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('client_freight_tables', [
            'client_id' => $client->id,
            'name' => 'Tabela SP',
        ]);
    }

    public function test_financial_cannot_create_freight_table(): void
    {
        $user = $this->makeUser('Financial');
        $client = $this->makeClient($user);

        $this->actingAsTenant($user)
            ->post(route('clients.freight-tables.store', $client), [
                'name' => 'Tabela',
                'pricing_model' => 'fixed',
            ])
            ->assertForbidden();
    }

    public function test_freight_table_name_must_be_unique_per_client(): void
    {
        $user = $this->makeUser('Admin');
        $client = $this->makeClient($user);
        ClientFreightTable::factory()->create([
            'company_id' => $user->company_id,
            'client_id' => $client->id,
            'name' => 'Tabela SP',
        ]);

        $this->actingAsTenant($user)
            ->post(route('clients.freight-tables.store', $client), [
                'name' => 'Tabela SP',
                'pricing_model' => 'fixed',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_pricing_model_is_immutable(): void
    {
        $user = $this->makeUser('Admin');
        $table = ClientFreightTable::factory()->create([
            'company_id' => $user->company_id,
            'pricing_model' => 'fixed',
        ]);

        $this->actingAsTenant($user)
            ->put(route('freight-tables.update', $table), [
                'name' => $table->name,
                'pricing_model' => 'per_km',  // should be ignored
                'active' => true,
            ]);

        $this->assertDatabaseHas('client_freight_tables', [
            'id' => $table->id,
            'pricing_model' => 'fixed',
        ]);
    }

    public function test_freight_table_not_visible_to_other_tenant(): void
    {
        $userA = $this->makeUser('Admin');
        $table = ClientFreightTable::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);

        $this->actingAsTenant($userA)
            ->get(route('freight-tables.show', $table))
            ->assertNotFound();
    }

    public function test_operator_can_delete_freight_table(): void
    {
        $user = $this->makeUser('Operator');
        $table = ClientFreightTable::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)
            ->delete(route('freight-tables.destroy', $table))
            ->assertRedirect();

        $this->assertDatabaseMissing('client_freight_tables', ['id' => $table->id]);
    }
}
```

- [ ] **Step 2: Create FixedFreightRateCrudTest**

```php
// tests/Feature/Commercial/FixedFreightRateCrudTest.php
<?php
namespace Tests\Feature\Commercial;

use App\Models\User;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Identity\Actions\SeedCompanyRolesAction;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

class FixedFreightRateCrudTest extends TenantTestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, ?Company $company = null): User
    {
        $company ??= Company::factory()->create();
        app(SeedCompanyRolesAction::class)->handle($company);
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);
        return $user;
    }

    public function test_operator_can_create_fixed_rate(): void
    {
        $user = $this->makeUser('Operator');
        $table = ClientFreightTable::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)
            ->post(route('freight-tables.fixed-rates.store', $table), [
                'name' => 'Sorocaba 3',
                'price' => '1500.00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fixed_freight_rates', [
            'client_freight_table_id' => $table->id,
            'name' => 'Sorocaba 3',
        ]);
    }

    public function test_financial_cannot_create_fixed_rate(): void
    {
        $user = $this->makeUser('Financial');
        $table = ClientFreightTable::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)
            ->post(route('freight-tables.fixed-rates.store', $table), [
                'name' => 'Rate X',
                'price' => '100',
            ])
            ->assertForbidden();
    }

    public function test_rate_name_must_be_unique_per_table(): void
    {
        $user = $this->makeUser('Admin');
        $table = ClientFreightTable::factory()->create(['company_id' => $user->company_id]);
        FixedFreightRate::factory()->create([
            'company_id' => $user->company_id,
            'client_freight_table_id' => $table->id,
            'name' => 'Sorocaba 3',
        ]);

        $this->actingAsTenant($user)
            ->post(route('freight-tables.fixed-rates.store', $table), [
                'name' => 'Sorocaba 3',
                'price' => '200',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_rate_not_visible_to_other_tenant(): void
    {
        $userA = $this->makeUser('Admin');
        $rate = FixedFreightRate::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);

        $this->actingAsTenant($userA)
            ->get(route('fixed-rates.edit', $rate))
            ->assertNotFound();
    }
}
```

- [ ] **Step 3: Create PerKmFreightRateCrudTest**

```php
// tests/Feature/Commercial/PerKmFreightRateCrudTest.php
<?php
namespace Tests\Feature\Commercial;

use App\Models\User;
use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\PerKmFreightRate;
use App\Modules\Identity\Actions\SeedCompanyRolesAction;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

class PerKmFreightRateCrudTest extends TenantTestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, ?Company $company = null): User
    {
        $company ??= Company::factory()->create();
        app(SeedCompanyRolesAction::class)->handle($company);
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);
        return $user;
    }

    private function makeClient(User $user): Client
    {
        return Client::factory()->create([
            'company_id' => $user->company_id,
            'document' => '11144477735',
        ]);
    }

    public function test_operator_can_create_per_km_rate(): void
    {
        $user = $this->makeUser('Operator');
        $client = $this->makeClient($user);

        $this->actingAsTenant($user)
            ->post(route('clients.per-km-rates.store', $client), [
                'state' => 'SP',
                'rate_per_km' => '3.5000',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('per_km_freight_rates', [
            'client_id' => $client->id,
            'state' => 'SP',
        ]);
    }

    public function test_invalid_state_is_rejected(): void
    {
        $user = $this->makeUser('Admin');
        $client = $this->makeClient($user);

        $this->actingAsTenant($user)
            ->post(route('clients.per-km-rates.store', $client), [
                'state' => 'XX',
                'rate_per_km' => '3.00',
            ])
            ->assertSessionHasErrors('state');
    }

    public function test_state_must_be_unique_per_client(): void
    {
        $user = $this->makeUser('Admin');
        $client = $this->makeClient($user);
        PerKmFreightRate::factory()->create([
            'company_id' => $user->company_id,
            'client_id' => $client->id,
            'state' => 'SP',
        ]);

        $this->actingAsTenant($user)
            ->post(route('clients.per-km-rates.store', $client), [
                'state' => 'SP',
                'rate_per_km' => '5.00',
            ])
            ->assertSessionHasErrors('state');
    }

    public function test_financial_cannot_create_per_km_rate(): void
    {
        $user = $this->makeUser('Financial');
        $client = $this->makeClient($user);

        $this->actingAsTenant($user)
            ->post(route('clients.per-km-rates.store', $client), [
                'state' => 'SP',
                'rate_per_km' => '3.00',
            ])
            ->assertForbidden();
    }

    public function test_rate_not_visible_to_other_tenant(): void
    {
        $userA = $this->makeUser('Admin');
        $rate = PerKmFreightRate::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);

        $this->actingAsTenant($userA)
            ->get(route('per-km-rates.edit', $rate))
            ->assertNotFound();
    }
}
```

- [ ] **Step 4: Run all Commercial tests**

```bash
php artisan test tests/Feature/Commercial/
```

Expected: some pass, some fail. Fix failures — common issues:
- Route name mismatches (`freight-tables.show` etc) — check `php artisan route:list | grep freight`
- Factory `company_id` propagation in child models

- [ ] **Step 5: Run full suite**

```bash
php artisan test
```

Expected: all passing

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/Commercial/
git commit -m "test(commercial): add freight table, fixed rate, and per-km rate tests"
```

---

## Task 9: Frontend Vue Pages

**Files:** 10 Vue pages in `resources/js/Pages/Commercial/`

- [ ] **Step 1: Create Clients/Index.vue**

```vue
<!-- resources/js/Pages/Commercial/Clients/Index.vue -->
<template>
  <div>
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-xl font-semibold">Clients</h1>
      <Link v-if="$page.props.auth.user.can?.['clients.manage']"
            :href="route('clients.create')"
            class="px-4 py-2 bg-blue-600 text-white rounded">New Client</Link>
    </div>

    <div class="mb-4 flex gap-2">
      <input v-model="filters.search" type="text" placeholder="Search name or document..."
             class="border rounded px-3 py-2 w-64" @input="search" />
      <select v-model="filters.active" class="border rounded px-3 py-2" @change="search">
        <option value="">All</option>
        <option value="1">Active</option>
        <option value="0">Inactive</option>
      </select>
    </div>

    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left p-2">Name</th>
          <th class="text-left p-2">Document</th>
          <th class="text-left p-2">Email</th>
          <th class="text-left p-2">Active</th>
          <th class="p-2"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="client in clients.data" :key="client.id" class="border-t">
          <td class="p-2">{{ client.name }}</td>
          <td class="p-2">{{ client.document }}</td>
          <td class="p-2">{{ client.email ?? '—' }}</td>
          <td class="p-2">{{ client.active ? 'Yes' : 'No' }}</td>
          <td class="p-2 flex gap-2">
            <Link :href="route('clients.edit', client.id)" class="text-blue-600">Edit</Link>
            <button v-if="$page.props.auth.user.can?.['clients.delete']"
                    @click="destroy(client)" class="text-red-600">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import { Link, router } from '@inertiajs/vue3'

export default {
  components: { Link },
  props: {
    clients: Object,
    filters: Object,
  },
  data() {
    return {
      filters: { search: this.filters?.search ?? '', active: this.filters?.active ?? '' },
    }
  },
  methods: {
    search() {
      router.get(route('clients.index'), this.filters, { preserveState: true, replace: true })
    },
    destroy(client) {
      if (confirm(`Delete ${client.name}?`)) {
        router.delete(route('clients.destroy', client.id))
      }
    },
  },
}
</script>
```

- [ ] **Step 2: Create Clients/Create.vue**

```vue
<!-- resources/js/Pages/Commercial/Clients/Create.vue -->
<template>
  <form @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-4">New Client</h1>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">Name *</label>
        <input v-model="form.name" type="text" class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium">CPF / CNPJ *</label>
        <input v-model="form.document" type="text" placeholder="Digits only" class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.document" class="text-red-500 text-sm">{{ form.errors.document }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium">Email</label>
        <input v-model="form.email" type="email" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">Phone</label>
        <input v-model="form.phone" type="text" class="border rounded px-3 py-2 w-full" />
      </div>
    </div>

    <div class="mt-4 flex items-center gap-2">
      <input v-model="form.active" type="checkbox" id="active" />
      <label for="active" class="text-sm">Active</label>
    </div>

    <div class="mt-6 flex gap-2">
      <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
      <Link :href="route('clients.index')" class="px-4 py-2 border rounded">Cancel</Link>
    </div>
  </form>
</template>

<script>
import { Link, useForm } from '@inertiajs/vue3'

export default {
  components: { Link },
  setup() {
    const form = useForm({
      name: '',
      document: '',
      email: '',
      phone: '',
      active: true,
    })
    return { form }
  },
  methods: {
    submit() {
      this.form.post(route('clients.store'))
    },
  },
}
</script>
```

- [ ] **Step 3: Create Clients/Edit.vue**

```vue
<!-- resources/js/Pages/Commercial/Clients/Edit.vue -->
<template>
  <form @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-4">Edit Client</h1>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">Name *</label>
        <input v-model="form.name" type="text" class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium">CPF / CNPJ *</label>
        <input v-model="form.document" type="text" class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.document" class="text-red-500 text-sm">{{ form.errors.document }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium">Email</label>
        <input v-model="form.email" type="email" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">Phone</label>
        <input v-model="form.phone" type="text" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">Street</label>
        <input v-model="form.address_street" type="text" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">City</label>
        <input v-model="form.address_city" type="text" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">State (UF)</label>
        <input v-model="form.address_state" type="text" maxlength="2" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">ZIP</label>
        <input v-model="form.address_zip" type="text" maxlength="8" class="border rounded px-3 py-2 w-full" />
      </div>
    </div>

    <div class="mt-4 flex items-center gap-2">
      <input v-model="form.active" type="checkbox" id="active" />
      <label for="active" class="text-sm">Active</label>
    </div>

    <div class="mt-6 flex gap-2">
      <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
      <Link :href="route('clients.index')" class="px-4 py-2 border rounded">Cancel</Link>
    </div>
  </form>
</template>

<script>
import { Link, useForm } from '@inertiajs/vue3'

export default {
  components: { Link },
  props: { client: Object },
  setup(props) {
    const form = useForm({
      name: props.client.name,
      document: props.client.document,
      email: props.client.email ?? '',
      phone: props.client.phone ?? '',
      address_street: props.client.address_street ?? '',
      address_number: props.client.address_number ?? '',
      address_complement: props.client.address_complement ?? '',
      address_neighborhood: props.client.address_neighborhood ?? '',
      address_city: props.client.address_city ?? '',
      address_state: props.client.address_state ?? '',
      address_zip: props.client.address_zip ?? '',
      active: props.client.active,
    })
    return { form }
  },
  methods: {
    submit() {
      this.form.put(route('clients.update', this.client.id))
    },
  },
}
</script>
```

- [ ] **Step 4: Create FreightTables/Create.vue**

```vue
<!-- resources/js/Pages/Commercial/FreightTables/Create.vue -->
<template>
  <form @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-4">New Freight Table — {{ client.name }}</h1>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">Name *</label>
        <input v-model="form.name" type="text" class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium">Pricing Model *</label>
        <select v-model="form.pricing_model" class="border rounded px-3 py-2 w-full">
          <option value="fixed">Fixed per route</option>
          <option value="per_km">Per km (by state)</option>
        </select>
      </div>
    </div>

    <div class="mt-4 flex items-center gap-2">
      <input v-model="form.active" type="checkbox" id="active" />
      <label for="active" class="text-sm">Active</label>
    </div>

    <div class="mt-6 flex gap-2">
      <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
      <Link :href="route('clients.edit', client.id)" class="px-4 py-2 border rounded">Cancel</Link>
    </div>
  </form>
</template>

<script>
import { Link, useForm } from '@inertiajs/vue3'

export default {
  components: { Link },
  props: { client: Object },
  setup(props) {
    const form = useForm({ name: '', pricing_model: 'fixed', active: true })
    return { form }
  },
  methods: {
    submit() {
      this.form.post(route('clients.freight-tables.store', this.client.id))
    },
  },
}
</script>
```

- [ ] **Step 5: Create FreightTables/Edit.vue**

```vue
<!-- resources/js/Pages/Commercial/FreightTables/Edit.vue -->
<template>
  <form @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-2">Edit Freight Table</h1>
    <p class="text-sm text-gray-500 mb-4">Pricing model: <strong>{{ freightTable.pricing_model }}</strong> (cannot be changed)</p>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">Name *</label>
        <input v-model="form.name" type="text" class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</p>
      </div>
    </div>

    <div class="mt-4 flex items-center gap-2">
      <input v-model="form.active" type="checkbox" id="active" />
      <label for="active" class="text-sm">Active</label>
    </div>

    <div class="mt-6 flex gap-2">
      <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
      <Link :href="route('freight-tables.show', freightTable.id)" class="px-4 py-2 border rounded">Cancel</Link>
    </div>
  </form>
</template>

<script>
import { Link, useForm } from '@inertiajs/vue3'

export default {
  components: { Link },
  props: { freightTable: Object },
  setup(props) {
    const form = useForm({ name: props.freightTable.name, active: props.freightTable.active })
    return { form }
  },
  methods: {
    submit() {
      this.form.put(route('freight-tables.update', this.freightTable.id))
    },
  },
}
</script>
```

- [ ] **Step 6: Create FreightTables/Show.vue**

```vue
<!-- resources/js/Pages/Commercial/FreightTables/Show.vue -->
<template>
  <div>
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-xl font-semibold">{{ freightTable.name }}</h1>
      <div class="flex gap-2">
        <Link :href="route('freight-tables.edit', freightTable.id)" class="px-3 py-1 border rounded text-sm">Edit</Link>
        <Link v-if="freightTable.pricing_model === 'fixed'"
              :href="route('freight-tables.fixed-rates.create', freightTable.id)"
              class="px-3 py-1 bg-blue-600 text-white rounded text-sm">Add Rate</Link>
      </div>
    </div>

    <p class="text-sm text-gray-500 mb-4">Client: {{ freightTable.client.name }} · Model: {{ freightTable.pricing_model }}</p>

    <table class="w-full text-sm" v-if="freightTable.fixed_rates?.length">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left p-2">Route Name</th>
          <th class="text-left p-2">Price</th>
          <th class="text-left p-2">Avg km</th>
          <th class="text-left p-2">Tolls</th>
          <th class="text-left p-2">Fuel Cost</th>
          <th class="p-2"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="rate in freightTable.fixed_rates" :key="rate.id" class="border-t">
          <td class="p-2">{{ rate.name }}</td>
          <td class="p-2">{{ rate.price }}</td>
          <td class="p-2">{{ rate.avg_km ?? '—' }}</td>
          <td class="p-2">{{ rate.tolls ?? '—' }}</td>
          <td class="p-2">{{ rate.fuel_cost ?? '—' }}</td>
          <td class="p-2 flex gap-2">
            <Link :href="route('fixed-rates.edit', rate.id)" class="text-blue-600">Edit</Link>
            <button @click="destroyRate(rate)" class="text-red-600">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
    <p v-else class="text-gray-400 text-sm">No rates yet.</p>
  </div>
</template>

<script>
import { Link, router } from '@inertiajs/vue3'

export default {
  components: { Link },
  props: { freightTable: Object },
  methods: {
    destroyRate(rate) {
      if (confirm(`Delete rate "${rate.name}"?`)) {
        router.delete(route('fixed-rates.destroy', rate.id))
      }
    },
  },
}
</script>
```

- [ ] **Step 7: Create FixedRates/Create.vue and Edit.vue**

```vue
<!-- resources/js/Pages/Commercial/FixedRates/Create.vue -->
<template>
  <form @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-4">New Rate — {{ freightTable.name }}</h1>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">Route Name *</label>
        <input v-model="form.name" type="text" placeholder='e.g. "Sorocaba 3"' class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium">Price (R$) *</label>
        <input v-model="form.price" type="number" step="0.01" min="0" class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.price" class="text-red-500 text-sm">{{ form.errors.price }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium">Avg km</label>
        <input v-model="form.avg_km" type="number" step="0.01" min="0" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">Tolls (R$)</label>
        <input v-model="form.tolls" type="number" step="0.01" min="0" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">Fuel Cost (R$)</label>
        <input v-model="form.fuel_cost" type="number" step="0.01" min="0" class="border rounded px-3 py-2 w-full" />
      </div>
    </div>
    <div class="mt-6 flex gap-2">
      <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
      <Link :href="route('freight-tables.show', freightTable.id)" class="px-4 py-2 border rounded">Cancel</Link>
    </div>
  </form>
</template>

<script>
import { Link, useForm } from '@inertiajs/vue3'

export default {
  components: { Link },
  props: { freightTable: Object },
  setup() {
    const form = useForm({ name: '', price: '', avg_km: '', tolls: '', fuel_cost: '' })
    return { form }
  },
  methods: {
    submit() {
      this.form.post(route('freight-tables.fixed-rates.store', this.freightTable.id))
    },
  },
}
</script>
```

```vue
<!-- resources/js/Pages/Commercial/FixedRates/Edit.vue -->
<template>
  <form @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-4">Edit Rate</h1>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">Route Name *</label>
        <input v-model="form.name" type="text" class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium">Price (R$) *</label>
        <input v-model="form.price" type="number" step="0.01" min="0" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">Avg km</label>
        <input v-model="form.avg_km" type="number" step="0.01" min="0" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">Tolls (R$)</label>
        <input v-model="form.tolls" type="number" step="0.01" min="0" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">Fuel Cost (R$)</label>
        <input v-model="form.fuel_cost" type="number" step="0.01" min="0" class="border rounded px-3 py-2 w-full" />
      </div>
    </div>
    <div class="mt-6 flex gap-2">
      <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
      <Link :href="route('freight-tables.show', rate.freight_table.id)" class="px-4 py-2 border rounded">Cancel</Link>
    </div>
  </form>
</template>

<script>
import { Link, useForm } from '@inertiajs/vue3'

export default {
  components: { Link },
  props: { rate: Object },
  setup(props) {
    const form = useForm({
      name: props.rate.name,
      price: props.rate.price,
      avg_km: props.rate.avg_km ?? '',
      tolls: props.rate.tolls ?? '',
      fuel_cost: props.rate.fuel_cost ?? '',
    })
    return { form }
  },
  methods: {
    submit() {
      this.form.put(route('fixed-rates.update', this.rate.id))
    },
  },
}
</script>
```

- [ ] **Step 8: Create PerKmRates/Create.vue and Edit.vue**

```vue
<!-- resources/js/Pages/Commercial/PerKmRates/Create.vue -->
<template>
  <form @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-4">New Per-km Rate — {{ client.name }}</h1>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">State (UF) *</label>
        <select v-model="form.state" class="border rounded px-3 py-2 w-full">
          <option value="">Select...</option>
          <option v-for="uf in states" :key="uf" :value="uf">{{ uf }}</option>
        </select>
        <p v-if="form.errors.state" class="text-red-500 text-sm">{{ form.errors.state }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium">Rate per km (R$/km) *</label>
        <input v-model="form.rate_per_km" type="number" step="0.0001" min="0" class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.rate_per_km" class="text-red-500 text-sm">{{ form.errors.rate_per_km }}</p>
      </div>
    </div>
    <div class="mt-6 flex gap-2">
      <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
      <Link :href="route('clients.edit', client.id)" class="px-4 py-2 border rounded">Cancel</Link>
    </div>
  </form>
</template>

<script>
import { Link, useForm } from '@inertiajs/vue3'

export default {
  components: { Link },
  props: { client: Object },
  data() {
    return {
      states: ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG',
               'PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'],
    }
  },
  setup() {
    const form = useForm({ state: '', rate_per_km: '' })
    return { form }
  },
  methods: {
    submit() {
      this.form.post(route('clients.per-km-rates.store', this.client.id))
    },
  },
}
</script>
```

```vue
<!-- resources/js/Pages/Commercial/PerKmRates/Edit.vue -->
<template>
  <form @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-4">Edit Per-km Rate — {{ rate.client.name }}</h1>
    <p class="text-sm text-gray-500 mb-4">State: <strong>{{ rate.state }}</strong> (cannot be changed)</p>
    <div>
      <label class="block text-sm font-medium">Rate per km (R$/km) *</label>
      <input v-model="form.rate_per_km" type="number" step="0.0001" min="0" class="border rounded px-3 py-2 w-48" />
      <p v-if="form.errors.rate_per_km" class="text-red-500 text-sm">{{ form.errors.rate_per_km }}</p>
    </div>
    <div class="mt-6 flex gap-2">
      <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
      <Link :href="route('clients.edit', rate.client.id)" class="px-4 py-2 border rounded">Cancel</Link>
    </div>
  </form>
</template>

<script>
import { Link, useForm } from '@inertiajs/vue3'

export default {
  components: { Link },
  props: { rate: Object },
  setup(props) {
    const form = useForm({ rate_per_km: props.rate.rate_per_km })
    return { form }
  },
  methods: {
    submit() {
      this.form.put(route('per-km-rates.update', this.rate.id))
    },
  },
}
</script>
```

- [ ] **Step 9: Commit all frontend pages**

```bash
git add resources/js/Pages/Commercial/
git commit -m "feat(commercial): add all Inertia frontend pages"
```

---

## Task 10: Final Verification

- [ ] **Step 1: Run full test suite**

```bash
php artisan test
```

Expected: all passing (44 original + all Commercial tests)

- [ ] **Step 2: Run static analysis**

```bash
composer stan
```

Fix any Larastan errors (commonly: missing return types, undefined properties on models).

- [ ] **Step 3: Run code style**

```bash
composer lint:fix
```

- [ ] **Step 4: Verify RLS coverage**

```bash
php artisan test --filter RlsCoverageTest
```

Expected: 1 passed

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "feat(commercial): Epic 03 complete — clients, freight tables, and rates"
```

---

## Known Gotchas

1. **Factory `document` uniqueness**: All factories that create `Client` with a fixed document will collide if two clients share a company. Use `fake()->unique()` or call with explicit `company_id` from a fresh Company.

2. **`cpf_ou_cnpj` rule registration**: `geekcom/validator-docs` registers its rules via a ServiceProvider. If the rule is not found, run `php artisan package:discover`.

3. **Shallow route names**: With `->shallow()`, nested routes use the parent prefix only for `index/create/store`. Singleton routes (`show/edit/update/destroy`) use just the child resource name. Verify with `php artisan route:list | grep commercial`.

4. **RLS and `app.current_company_id`**: Tests that don't call `actingAsTenant()` will skip RLS (the policy allows null). That's intentional for the coverage test but means you must use `actingAsTenant()` in all feature tests or RLS bypass goes untested.

5. **`company_id` on FixedFreightRate**: The `BelongsToCompany` trait's `creating` hook fills `company_id` from `auth()->user()->company_id`, but `CreateFixedFreightRateAction` explicitly passes it from the parent table to avoid any mismatch. Keep both.
