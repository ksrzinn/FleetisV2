# Epic 4 — Operations: Freights & Lifecycle — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the freight creation wizard and full lifecycle state machine (ToStart → InRoute → Finished → AwaitingPayment → Completed) with cost capture, audit trail, and computed fuel estimates.

**Architecture:** `app/Modules/Operations/` module. State machine via `spatie/laravel-model-states`. Thin controllers → Action classes. 4-step Inertia wizard (Vue 3 Options API). Transitions on a freight show page with modal confirmation for InRoute→Finished.

**Tech Stack:** Laravel 11, Inertia.js, Vue 3 Options API, Tailwind, `spatie/laravel-model-states ^2`, `spatie/laravel-activitylog ^4`, PostgreSQL trigger.

**Working directory:** `/var/www/html/projects/fleetis-v2/.worktrees/epic-04-operations`

**Run tests with:** `php artisan test` from the worktree directory.

---

## File Map

**New files:**
- `database/migrations/2026_04_20_000001_add_consumo_medio_to_vehicles_table.php`
- `database/migrations/2026_04_20_000002_create_freights_table.php`
- `database/migrations/2026_04_20_000003_create_freight_status_history_table.php`
- `database/migrations/rls/2026_04_20_000004_freight_rls_policies.php`
- `app/Modules/Operations/States/FreightState.php`
- `app/Modules/Operations/States/ToStart.php`
- `app/Modules/Operations/States/InRoute.php`
- `app/Modules/Operations/States/Finished.php`
- `app/Modules/Operations/States/AwaitingPayment.php`
- `app/Modules/Operations/States/Completed.php`
- `app/Modules/Operations/Models/Freight.php`
- `app/Modules/Operations/Models/FreightStatusHistory.php`
- `app/Modules/Operations/Observers/FreightObserver.php`
- `app/Modules/Operations/Policies/FreightPolicy.php`
- `app/Modules/Operations/Http/Requests/StoreFreightRequest.php`
- `app/Modules/Operations/Http/Requests/TransitionFreightRequest.php`
- `app/Modules/Operations/Actions/CreateFreightAction.php`
- `app/Modules/Operations/Actions/TransitionFreightAction.php`
- `app/Modules/Operations/Events/FreightEnteredAwaitingPayment.php`
- `app/Modules/Operations/Listeners/CreateReceivableForFreight.php`
- `app/Modules/Operations/Http/Controllers/FreightController.php`
- `app/Modules/Operations/Http/Controllers/FreightRatesController.php`
- `database/factories/Operations/FreightFactory.php`
- `tests/Feature/Operations/FreightControllerTest.php`
- `tests/Feature/Operations/FreightStateTransitionTest.php`
- `resources/js/Pages/Operations/Index.vue`
- `resources/js/Pages/Operations/Create.vue`
- `resources/js/Pages/Operations/Show.vue`

**Modified files:**
- `app/Modules/Fleet/Models/Vehicle.php` — add `consumo_medio` to fillable + casts
- `app/Modules/Fleet/Http/Requests/VehicleRequest.php` — add `consumo_medio` rule
- `resources/js/Pages/Fleet/Vehicles/Form.vue` — add `consumo_medio` input
- `app/Providers/AppServiceProvider.php` — register FreightPolicy + FreightObserver
- `resources/js/Layouts/AuthenticatedLayout.vue` — add Fretes nav item
- `routes/web.php` — add freight routes

---

## Task 1: Install Packages

**Files:** `composer.json`

- [ ] **Step 1: Install spatie packages**

```bash
composer require spatie/laravel-model-states:^2 spatie/laravel-activitylog:^4
```

Expected: packages installed without error.

- [ ] **Step 2: Publish activitylog migration**

```bash
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate
```

- [ ] **Step 3: Run tests to confirm clean baseline**

```bash
php artisan test
```

Expected: all existing tests pass.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock database/migrations
git commit -m "chore: install spatie/laravel-model-states and spatie/laravel-activitylog"
```

---

## Task 2: Add consumo_medio to Vehicles

**Files:**
- Create: `database/migrations/2026_04_20_000001_add_consumo_medio_to_vehicles_table.php`
- Modify: `app/Modules/Fleet/Models/Vehicle.php`
- Modify: `app/Modules/Fleet/Http/Requests/VehicleRequest.php`
- Modify: `resources/js/Pages/Fleet/Vehicles/Form.vue`

- [ ] **Step 1: Write failing test**

Add to `tests/Feature/Fleet/VehicleControllerTest.php`:

```php
public function test_operator_can_set_consumo_medio_on_vehicle(): void
{
    $user = $this->makeUserWithRole('Operator');
    $type = VehicleType::factory()->create();

    $this->actingAsTenant($user)->post('/vehicles', [
        'kind' => 'vehicle',
        'vehicle_type_id' => $type->id,
        'license_plate' => 'ABC-1234',
        'brand' => 'Volvo',
        'model' => 'FH',
        'year' => 2020,
        'consumo_medio' => 8.5,
    ]);

    $this->assertDatabaseHas('vehicles', [
        'company_id' => $user->company_id,
        'consumo_medio' => 8.5,
    ]);
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --filter test_operator_can_set_consumo_medio_on_vehicle
```

Expected: FAIL — column does not exist.

- [ ] **Step 3: Create migration**

```php
<?php
// database/migrations/2026_04_20_000001_add_consumo_medio_to_vehicles_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('consumo_medio', 8, 2)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('consumo_medio');
        });
    }
};
```

- [ ] **Step 4: Update Vehicle model**

In `app/Modules/Fleet/Models/Vehicle.php`, add `consumo_medio` to fillable and casts:

```php
protected $fillable = [
    'kind', 'vehicle_type_id', 'license_plate', 'renavam',
    'brand', 'model', 'year', 'notes', 'consumo_medio', 'active',
];

protected $casts = ['active' => 'boolean', 'year' => 'integer', 'consumo_medio' => 'decimal:2'];
```

- [ ] **Step 5: Update VehicleRequest**

In `app/Modules/Fleet/Http/Requests/VehicleRequest.php`, add to rules():

```php
'consumo_medio' => ['nullable', 'numeric', 'min:0.1', 'max:99.99'],
```

- [ ] **Step 6: Run migration and test**

```bash
php artisan migrate
php artisan test --filter test_operator_can_set_consumo_medio_on_vehicle
```

Expected: PASS.

- [ ] **Step 7: Add consumo_medio field to Vehicle Form.vue**

In `resources/js/Pages/Fleet/Vehicles/Form.vue`, add `consumo_medio` to the form setup:

```javascript
// In setup(props):
consumo_medio: props.vehicle?.consumo_medio ?? '',
```

Add the input after the `year` field, inside the grid:

```html
<!-- Consumo Médio -->
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1.5">Consumo Médio (km/L)</label>
    <input
        v-model="form.consumo_medio"
        type="number"
        step="0.1"
        min="0.1"
        max="99.99"
        placeholder="Ex: 8.5"
        class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
    />
    <p class="mt-1 text-xs text-gray-400">Quantos km o veículo percorre com 1 litro</p>
    <p v-if="form.errors.consumo_medio" class="mt-1.5 text-xs text-red-600">{{ form.errors.consumo_medio }}</p>
</div>
```

- [ ] **Step 8: Run full tests**

```bash
php artisan test
```

Expected: all pass.

- [ ] **Step 9: Commit**

```bash
git add .
git commit -m "feat(fleet): add consumo_medio (km/L) field to vehicles"
```

---

## Task 3: Freights and Status History Migrations

**Files:**
- Create: `database/migrations/2026_04_20_000002_create_freights_table.php`
- Create: `database/migrations/2026_04_20_000003_create_freight_status_history_table.php`

- [ ] **Step 1: Create freights migration**

```php
<?php
// database/migrations/2026_04_20_000002_create_freights_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->foreignId('trailer_id')->nullable()->constrained('vehicles');
            $table->foreignId('driver_id')->nullable()->constrained('drivers');
            $table->enum('pricing_model', ['fixed', 'per_km']);
            $table->foreignId('fixed_rate_id')->nullable()->constrained('fixed_freight_rates');
            $table->foreignId('per_km_rate_id')->nullable()->constrained('per_km_freight_rates');
            $table->string('origin', 150)->nullable();
            $table->string('destination', 150)->nullable();
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->decimal('toll', 10, 2)->nullable();
            $table->decimal('fuel_price_per_liter', 8, 4)->nullable();
            $table->decimal('freight_value', 12, 2)->nullable();
            $table->string('status')->default('to_start');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'client_id']);
            $table->index(['company_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freights');
    }
};
```

- [ ] **Step 2: Create freight_status_history migration**

```php
<?php
// database/migrations/2026_04_20_000003_create_freight_status_history_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freight_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('freight_id')->constrained('freights')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['freight_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freight_status_history');
    }
};
```

- [ ] **Step 3: Run migrations**

```bash
php artisan migrate
```

Expected: no errors, two new tables created.

- [ ] **Step 4: Commit**

```bash
git add database/migrations
git commit -m "feat(operations): add freights and freight_status_history migrations"
```

---

## Task 4: State Classes

**Files:**
- Create: `app/Modules/Operations/States/FreightState.php`
- Create: `app/Modules/Operations/States/ToStart.php`
- Create: `app/Modules/Operations/States/InRoute.php`
- Create: `app/Modules/Operations/States/Finished.php`
- Create: `app/Modules/Operations/States/AwaitingPayment.php`
- Create: `app/Modules/Operations/States/Completed.php`

- [ ] **Step 1: Create base FreightState with transition config**

```php
<?php
// app/Modules/Operations/States/FreightState.php

namespace App\Modules\Operations\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class FreightState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(ToStart::class)
            ->allowTransition(ToStart::class, InRoute::class)
            ->allowTransition(InRoute::class, Finished::class)
            ->allowTransition(Finished::class, AwaitingPayment::class)
            ->allowTransition(AwaitingPayment::class, Completed::class);
    }

    abstract public function label(): string;
}
```

- [ ] **Step 2: Create each state class**

```php
<?php
// app/Modules/Operations/States/ToStart.php
namespace App\Modules\Operations\States;

class ToStart extends FreightState
{
    public function label(): string { return 'A Iniciar'; }
}
```

```php
<?php
// app/Modules/Operations/States/InRoute.php
namespace App\Modules\Operations\States;

class InRoute extends FreightState
{
    public function label(): string { return 'Em Rota'; }
}
```

```php
<?php
// app/Modules/Operations/States/Finished.php
namespace App\Modules\Operations\States;

class Finished extends FreightState
{
    public function label(): string { return 'Finalizado'; }
}
```

```php
<?php
// app/Modules/Operations/States/AwaitingPayment.php
namespace App\Modules\Operations\States;

class AwaitingPayment extends FreightState
{
    public function label(): string { return 'Aguardando Pagamento'; }
}
```

```php
<?php
// app/Modules/Operations/States/Completed.php
namespace App\Modules\Operations\States;

class Completed extends FreightState
{
    public function label(): string { return 'Concluído'; }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Modules/Operations/States/
git commit -m "feat(operations): add freight state classes"
```

---

## Task 5: Freight and FreightStatusHistory Models

**Files:**
- Create: `app/Modules/Operations/Models/Freight.php`
- Create: `app/Modules/Operations/Models/FreightStatusHistory.php`
- Create: `app/Modules/Operations/Observers/FreightObserver.php`
- Create: `database/factories/Operations/FreightFactory.php`

- [ ] **Step 1: Create Freight model**

```php
<?php
// app/Modules/Operations/Models/Freight.php

namespace App\Modules\Operations\Models;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Commercial\Models\PerKmFreightRate;
use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\States\FreightState;
use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Operations\FreightFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\ModelStates\HasStates;

class Freight extends Model
{
    /** @use HasFactory<FreightFactory> */
    use BelongsToCompany, HasFactory, HasStates, LogsActivity, SoftDeletes;

    protected $fillable = [
        'client_id', 'vehicle_id', 'trailer_id', 'driver_id',
        'pricing_model', 'fixed_rate_id', 'per_km_rate_id',
        'origin', 'destination',
        'distance_km', 'toll', 'fuel_price_per_liter', 'freight_value',
        'status', 'started_at', 'finished_at', 'completed_at',
    ];

    protected $casts = [
        'status'              => FreightState::class,
        'distance_km'         => 'decimal:2',
        'toll'                => 'decimal:2',
        'fuel_price_per_liter'=> 'decimal:4',
        'freight_value'       => 'decimal:2',
        'started_at'          => 'datetime',
        'finished_at'         => 'datetime',
        'completed_at'        => 'datetime',
    ];

    protected static function newFactory(): FreightFactory
    {
        return FreightFactory::new();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status'])->logOnlyDirty();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'trailer_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function fixedRate(): BelongsTo
    {
        return $this->belongsTo(FixedFreightRate::class, 'fixed_rate_id');
    }

    public function perKmRate(): BelongsTo
    {
        return $this->belongsTo(PerKmFreightRate::class, 'per_km_rate_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(FreightStatusHistory::class)->orderBy('occurred_at');
    }

    public function estimatedLiters(): ?float
    {
        $consumo = $this->vehicle?->consumo_medio;
        if (! $this->distance_km || ! $consumo) {
            return null;
        }

        return round((float) $this->distance_km / (float) $consumo, 2);
    }

    public function estimatedFuelCost(): ?float
    {
        $liters = $this->estimatedLiters();
        if (! $liters || ! $this->fuel_price_per_liter) {
            return null;
        }

        return round($liters * (float) $this->fuel_price_per_liter, 2);
    }
}
```

- [ ] **Step 2: Create FreightStatusHistory model**

```php
<?php
// app/Modules/Operations/Models/FreightStatusHistory.php

namespace App\Modules\Operations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreightStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'freight_id', 'from_status', 'to_status', 'user_id', 'notes', 'occurred_at',
    ];

    protected $casts = ['occurred_at' => 'datetime'];

    public function freight(): BelongsTo
    {
        return $this->belongsTo(Freight::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 3: Create FreightObserver — auto-records status history**

```php
<?php
// app/Modules/Operations/Observers/FreightObserver.php

namespace App\Modules\Operations\Observers;

use App\Modules\Operations\Models\Freight;
use App\Modules\Operations\Models\FreightStatusHistory;

class FreightObserver
{
    public function updated(Freight $freight): void
    {
        if (! $freight->wasChanged('status')) {
            return;
        }

        FreightStatusHistory::create([
            'freight_id'  => $freight->id,
            'from_status' => $freight->getOriginal('status'),
            'to_status'   => (string) $freight->status,
            'user_id'     => auth()->id(),
            'occurred_at' => now(),
        ]);
    }

    public function created(Freight $freight): void
    {
        FreightStatusHistory::create([
            'freight_id'  => $freight->id,
            'from_status' => null,
            'to_status'   => (string) $freight->status,
            'user_id'     => auth()->id(),
            'occurred_at' => now(),
        ]);
    }
}
```

- [ ] **Step 4: Create FreightFactory**

```php
<?php
// database/factories/Operations/FreightFactory.php

namespace Database\Factories\Operations;

use App\Modules\Commercial\Models\Client;
use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\Models\Freight;
use App\Modules\Operations\States\ToStart;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Freight> */
class FreightFactory extends Factory
{
    protected $model = Freight::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'company_id'    => $company->id,
            'client_id'     => Client::factory()->create(['company_id' => $company->id])->id,
            'vehicle_id'    => Vehicle::factory()->create(['company_id' => $company->id, 'kind' => 'vehicle'])->id,
            'trailer_id'    => null,
            'driver_id'     => Driver::factory()->create(['company_id' => $company->id])->id,
            'pricing_model' => 'fixed',
            'fixed_rate_id' => null,
            'per_km_rate_id'=> null,
            'origin'        => null,
            'destination'   => null,
            'status'        => ToStart::class,
        ];
    }

    public function inRoute(): static
    {
        return $this->state(['status' => \App\Modules\Operations\States\InRoute::class, 'started_at' => now()]);
    }

    public function finished(): static
    {
        return $this->state([
            'status'      => \App\Modules\Operations\States\Finished::class,
            'started_at'  => now()->subHours(3),
            'finished_at' => now(),
        ]);
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Operations/ database/factories/Operations/
git commit -m "feat(operations): add Freight model, FreightStatusHistory, and observer"
```

---

## Task 6: FreightPolicy + Registration

**Files:**
- Create: `app/Modules/Operations/Policies/FreightPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Write failing policy test**

Create `tests/Feature/Operations/FreightPolicyTest.php`:

```php
<?php

namespace Tests\Feature\Operations;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class FreightPolicyTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_financial_cannot_create_freight(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $response = $this->actingAsTenant($user)->get('/freights/create');
        $response->assertForbidden();
    }

    public function test_operator_can_view_freight_index(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $response = $this->actingAsTenant($user)->get('/freights');
        $response->assertOk();
    }

    public function test_operator_cannot_view_other_company_freight(): void
    {
        $userA = $this->makeUserWithRole('Operator');
        $freight = Freight::factory()->create(); // different company

        $response = $this->actingAsTenant($userA)->get("/freights/{$freight->id}");
        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to confirm failure**

```bash
php artisan test --filter FreightPolicyTest
```

Expected: FAIL — route not found / forbidden.

- [ ] **Step 3: Create FreightPolicy**

```php
<?php
// app/Modules/Operations/Policies/FreightPolicy.php

namespace App\Modules\Operations\Policies;

use App\Models\User;
use App\Modules\Operations\Models\Freight;
use App\Modules\Tenancy\Policies\TenantPolicy;

class FreightPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator', 'Financial']);
    }

    public function view(User $user, Freight $freight): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator', 'Financial'])
            && $this->belongsToTenant($user, $freight);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator']);
    }

    public function update(User $user, Freight $freight): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator'])
            && $this->belongsToTenant($user, $freight);
    }

    public function delete(User $user, Freight $freight): bool
    {
        return $user->hasRole('Admin')
            && $this->belongsToTenant($user, $freight);
    }

    public function transition(User $user, Freight $freight): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator'])
            && $this->belongsToTenant($user, $freight);
    }
}
```

- [ ] **Step 4: Register policy and observer in AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, add imports:

```php
use App\Modules\Operations\Models\Freight;
use App\Modules\Operations\Observers\FreightObserver;
use App\Modules\Operations\Policies\FreightPolicy;
```

In `boot()`:

```php
Gate::policy(Freight::class, FreightPolicy::class);
Freight::observe(FreightObserver::class);
```

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Operations/Policies/ app/Providers/AppServiceProvider.php
git commit -m "feat(operations): add FreightPolicy and register observer"
```

---

## Task 7: StoreFreightRequest and CreateFreightAction

**Files:**
- Create: `app/Modules/Operations/Http/Requests/StoreFreightRequest.php`
- Create: `app/Modules/Operations/Actions/CreateFreightAction.php`

- [ ] **Step 1: Write failing test for freight creation**

Add to `tests/Feature/Operations/FreightControllerTest.php`:

```php
<?php

namespace Tests\Feature\Operations;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Operations\Models\Freight;
use App\Modules\Operations\States\ToStart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class FreightControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_fixed_freight(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $type = VehicleType::factory()->create(['requires_trailer' => false]);
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id, 'vehicle_type_id' => $type->id, 'kind' => 'vehicle']);
        $driver = Driver::factory()->create(['company_id' => $user->company_id]);
        $client = Client::factory()->create(['company_id' => $user->company_id]);
        $table = ClientFreightTable::factory()->create(['company_id' => $user->company_id, 'client_id' => $client->id, 'pricing_model' => 'fixed']);
        $rate = FixedFreightRate::factory()->create(['company_id' => $user->company_id, 'client_freight_table_id' => $table->id]);

        $response = $this->actingAsTenant($user)->post('/freights', [
            'client_id' => $client->id,
            'pricing_model' => 'fixed',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'fixed_rate_id' => $rate->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('freights', [
            'company_id' => $user->company_id,
            'client_id' => $client->id,
            'status' => 'to_start',
        ]);
    }

    public function test_creating_freight_requires_trailer_when_vehicle_type_demands_it(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $type = VehicleType::factory()->create(['requires_trailer' => true]);
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id, 'vehicle_type_id' => $type->id, 'kind' => 'vehicle']);
        $driver = Driver::factory()->create(['company_id' => $user->company_id]);
        $client = Client::factory()->create(['company_id' => $user->company_id]);
        $table = ClientFreightTable::factory()->create(['company_id' => $user->company_id, 'client_id' => $client->id, 'pricing_model' => 'fixed']);
        $rate = FixedFreightRate::factory()->create(['company_id' => $user->company_id, 'client_freight_table_id' => $table->id]);

        $response = $this->actingAsTenant($user)->post('/freights', [
            'client_id' => $client->id,
            'pricing_model' => 'fixed',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'fixed_rate_id' => $rate->id,
            // trailer_id intentionally omitted
        ]);

        $response->assertSessionHasErrors('trailer_id');
    }

    public function test_index_does_not_leak_other_company_freights(): void
    {
        $userA = $this->makeUserWithRole('Operator');
        Freight::factory()->create(['company_id' => $userA->company_id]);
        Freight::factory()->create(); // other company

        $response = $this->actingAsTenant($userA)->get('/freights');
        $response->assertInertia(fn ($page) => $page->has('freights.data', 1));
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test --filter FreightControllerTest
```

Expected: FAIL — route not found.

- [ ] **Step 3: Create StoreFreightRequest**

```php
<?php
// app/Modules/Operations/Http/Requests/StoreFreightRequest.php

namespace App\Modules\Operations\Http\Requests;

use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

class StoreFreightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'client_id'     => ['required', 'exists:clients,id'],
            'pricing_model' => ['required', 'in:fixed,per_km'],
            'vehicle_id'    => ['required', 'exists:vehicles,id'],
            'trailer_id'    => ['nullable', 'exists:vehicles,id'],
            'driver_id'     => ['nullable', 'exists:drivers,id'],
            'fixed_rate_id' => ['required_if:pricing_model,fixed', 'nullable', 'exists:fixed_freight_rates,id'],
            'per_km_rate_id'=> ['required_if:pricing_model,per_km', 'nullable', 'exists:per_km_freight_rates,id'],
            'origin'        => ['required_if:pricing_model,per_km', 'nullable', 'string', 'max:150'],
            'destination'   => ['required_if:pricing_model,per_km', 'nullable', 'string', 'max:150'],
        ];

        // Enforce trailer when vehicle type requires it
        $vehicleId = $this->input('vehicle_id');
        if ($vehicleId) {
            $vehicle = Vehicle::with('vehicleType')->find($vehicleId);
            if ($vehicle?->vehicleType?->requires_trailer) {
                $rules['trailer_id'] = ['required', 'exists:vehicles,id'];
            }
        }

        return $rules;
    }
}
```

- [ ] **Step 4: Create CreateFreightAction**

```php
<?php
// app/Modules/Operations/Actions/CreateFreightAction.php

namespace App\Modules\Operations\Actions;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\Models\Freight;
use InvalidArgumentException;

class CreateFreightAction
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): Freight
    {
        // Layer 2: re-check trailer requirement
        $vehicle = Vehicle::with('vehicleType')->findOrFail($data['vehicle_id']);
        if ($vehicle->vehicleType->requires_trailer && empty($data['trailer_id'])) {
            throw new InvalidArgumentException('Trailer obrigatório para este tipo de veículo.');
        }

        return Freight::create($data);
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Operations/Http/Requests/ app/Modules/Operations/Actions/CreateFreightAction.php
git commit -m "feat(operations): add StoreFreightRequest and CreateFreightAction"
```

---

## Task 8: TransitionFreightRequest and TransitionFreightAction

**Files:**
- Create: `app/Modules/Operations/Http/Requests/TransitionFreightRequest.php`
- Create: `app/Modules/Operations/Actions/TransitionFreightAction.php`
- Create: `app/Modules/Operations/Events/FreightEnteredAwaitingPayment.php`
- Create: `app/Modules/Operations/Listeners/CreateReceivableForFreight.php`

- [ ] **Step 1: Write failing transition tests**

Create `tests/Feature/Operations/FreightStateTransitionTest.php`:

```php
<?php

namespace Tests\Feature\Operations;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Commercial\Models\FixedFreightRatePrice;
use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Operations\Models\Freight;
use App\Modules\Operations\States\Finished;
use App\Modules\Operations\States\InRoute;
use App\Modules\Operations\States\AwaitingPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Modules\Operations\Events\FreightEnteredAwaitingPayment;
use Tests\TenantTestCase;

class FreightStateTransitionTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_to_start_transitions_to_in_route(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->post("/freights/{$freight->id}/transition", [
            'transition' => 'to_in_route',
        ]);

        $this->assertDatabaseHas('freights', ['id' => $freight->id, 'status' => 'in_route']);
        $this->assertNotNull($freight->fresh()->started_at);
    }

    public function test_in_route_transitions_to_finished_with_toll_and_km(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $freight = Freight::factory()->inRoute()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->post("/freights/{$freight->id}/transition", [
            'transition' => 'to_finished',
            'toll' => 150.00,
            'distance_km' => 500,
            'fuel_price_per_liter' => 6.50,
        ]);

        $this->assertDatabaseHas('freights', [
            'id' => $freight->id,
            'status' => 'finished',
            'toll' => 150.00,
            'distance_km' => 500,
        ]);
        $this->assertNotNull($freight->fresh()->finished_at);
    }

    public function test_per_km_finish_requires_distance_km(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $freight = Freight::factory()->inRoute()->create([
            'company_id' => $user->company_id,
            'pricing_model' => 'per_km',
        ]);

        $response = $this->actingAsTenant($user)->post("/freights/{$freight->id}/transition", [
            'transition' => 'to_finished',
            'toll' => 100,
            // distance_km intentionally missing
        ]);

        $response->assertSessionHasErrors('distance_km');
    }

    public function test_finished_to_awaiting_payment_locks_freight_value_for_fixed(): void
    {
        Event::fake();

        $user = $this->makeUserWithRole('Operator');
        $type = VehicleType::factory()->create();
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id, 'vehicle_type_id' => $type->id]);
        $rate = FixedFreightRate::factory()->create(['company_id' => $user->company_id]);
        FixedFreightRatePrice::factory()->create([
            'company_id' => $user->company_id,
            'fixed_freight_rate_id' => $rate->id,
            'vehicle_type_id' => $type->id,
            'price' => 1200.00,
        ]);
        $freight = Freight::factory()->finished()->create([
            'company_id' => $user->company_id,
            'vehicle_id' => $vehicle->id,
            'pricing_model' => 'fixed',
            'fixed_rate_id' => $rate->id,
        ]);

        $this->actingAsTenant($user)->post("/freights/{$freight->id}/transition", [
            'transition' => 'to_awaiting_payment',
        ]);

        $this->assertDatabaseHas('freights', ['id' => $freight->id, 'freight_value' => 1200.00]);
        Event::assertDispatched(FreightEnteredAwaitingPayment::class);
    }

    public function test_status_history_is_recorded_on_transition(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->post("/freights/{$freight->id}/transition", [
            'transition' => 'to_in_route',
        ]);

        $this->assertDatabaseHas('freight_status_history', [
            'freight_id' => $freight->id,
            'from_status' => 'to_start',
            'to_status' => 'in_route',
        ]);
    }
}
```

- [ ] **Step 2: Run tests to confirm failure**

```bash
php artisan test --filter FreightStateTransitionTest
```

Expected: FAIL — route not found.

- [ ] **Step 3: Create event and stub listener**

```php
<?php
// app/Modules/Operations/Events/FreightEnteredAwaitingPayment.php

namespace App\Modules\Operations\Events;

use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Events\Dispatchable;

class FreightEnteredAwaitingPayment
{
    use Dispatchable;

    public function __construct(public readonly Freight $freight) {}
}
```

```php
<?php
// app/Modules/Operations/Listeners/CreateReceivableForFreight.php

namespace App\Modules\Operations\Listeners;

use App\Modules\Operations\Events\FreightEnteredAwaitingPayment;

class CreateReceivableForFreight
{
    // TODO Epic 6: create receivable from $event->freight
    public function handle(FreightEnteredAwaitingPayment $event): void {}
}
```

- [ ] **Step 4: Create TransitionFreightRequest**

```php
<?php
// app/Modules/Operations/Http/Requests/TransitionFreightRequest.php

namespace App\Modules\Operations\Http\Requests;

use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Http\FormRequest;

class TransitionFreightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Freight $freight */
        $freight = $this->route('freight');
        $transition = $this->input('transition');
        $rules = ['transition' => ['required', 'in:to_in_route,to_finished,to_awaiting_payment']];

        if ($transition === 'to_finished') {
            $rules['toll'] = ['nullable', 'numeric', 'min:0'];
            $rules['fuel_price_per_liter'] = ['nullable', 'numeric', 'min:0'];

            if ($freight->pricing_model === 'per_km') {
                $rules['distance_km'] = ['required', 'numeric', 'min:1'];
                $rules['toll'] = ['required', 'numeric', 'min:0'];
            } else {
                $rules['distance_km'] = ['nullable', 'numeric', 'min:1'];
            }
        }

        return $rules;
    }
}
```

- [ ] **Step 5: Create TransitionFreightAction**

```php
<?php
// app/Modules/Operations/Actions/TransitionFreightAction.php

namespace App\Modules\Operations\Actions;

use App\Modules\Commercial\Models\FixedFreightRatePrice;
use App\Modules\Commercial\Models\PerKmFreightRatePrice;
use App\Modules\Operations\Events\FreightEnteredAwaitingPayment;
use App\Modules\Operations\Models\Freight;
use App\Modules\Operations\States\AwaitingPayment;
use App\Modules\Operations\States\Finished;
use App\Modules\Operations\States\InRoute;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

class TransitionFreightAction
{
    /** @param array<string, mixed> $data */
    public function handle(Freight $freight, array $data): Freight
    {
        return match ($data['transition']) {
            'to_in_route'         => $this->toInRoute($freight),
            'to_finished'         => $this->toFinished($freight, $data),
            'to_awaiting_payment' => $this->toAwaitingPayment($freight),
            default               => throw new TransitionNotFound(),
        };
    }

    private function toInRoute(Freight $freight): Freight
    {
        $freight->status->transitionTo(InRoute::class);
        $freight->update(['started_at' => now()]);

        return $freight;
    }

    /** @param array<string, mixed> $data */
    private function toFinished(Freight $freight, array $data): Freight
    {
        $freight->status->transitionTo(Finished::class);
        $freight->update([
            'distance_km'          => $data['distance_km'] ?? null,
            'toll'                 => $data['toll'] ?? null,
            'fuel_price_per_liter' => $data['fuel_price_per_liter'] ?? null,
            'finished_at'          => now(),
        ]);

        return $freight;
    }

    private function toAwaitingPayment(Freight $freight): Freight
    {
        $freightValue = $this->computeFreightValue($freight);

        $freight->status->transitionTo(AwaitingPayment::class);
        $freight->update(['freight_value' => $freightValue]);

        FreightEnteredAwaitingPayment::dispatch($freight);

        return $freight;
    }

    private function computeFreightValue(Freight $freight): ?string
    {
        $vehicleTypeId = $freight->vehicle->vehicle_type_id;

        if ($freight->pricing_model === 'fixed') {
            return FixedFreightRatePrice::where('fixed_freight_rate_id', $freight->fixed_rate_id)
                ->where('vehicle_type_id', $vehicleTypeId)
                ->value('price');
        }

        $rate = PerKmFreightRatePrice::where('per_km_freight_rate_id', $freight->per_km_rate_id)
            ->where('vehicle_type_id', $vehicleTypeId)
            ->value('rate_per_km');

        if (! $rate || ! $freight->distance_km) {
            return null;
        }

        return (string) bcmul((string) $freight->distance_km, (string) $rate, 2);
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Operations/
git commit -m "feat(operations): add TransitionFreightAction, event, and stub listener"
```

---

## Task 9: FreightController, FreightRatesController, and Routes

**Files:**
- Create: `app/Modules/Operations/Http/Controllers/FreightController.php`
- Create: `app/Modules/Operations/Http/Controllers/FreightRatesController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create FreightController**

```php
<?php
// app/Modules/Operations/Http/Controllers/FreightController.php

namespace App\Modules\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commercial\Models\Client;
use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Operations\Actions\CreateFreightAction;
use App\Modules\Operations\Actions\TransitionFreightAction;
use App\Modules\Operations\Http\Requests\StoreFreightRequest;
use App\Modules\Operations\Http\Requests\TransitionFreightRequest;
use App\Modules\Operations\Models\Freight;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FreightController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Freight::class);

        $freights = Freight::with(['client', 'vehicle', 'driver'])
            ->when(request('status'), fn ($q, $s) => $q->where('status', $s))
            ->when(request('search'), fn ($q, $s) => $q->whereHas('client', fn ($cq) => $cq->where('name', 'ilike', "%{$s}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Operations/Index', [
            'freights' => $freights,
            'filters'  => request()->only('status', 'search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Freight::class);

        return Inertia::render('Operations/Create', [
            'clients'      => Client::where('active', true)->orderBy('name')->get(['id', 'name', 'document']),
            'vehicles'     => Vehicle::with('vehicleType')->where('active', true)->where('kind', 'vehicle')->orderBy('license_plate')->get(),
            'trailers'     => Vehicle::where('active', true)->where('kind', 'trailer')->orderBy('license_plate')->get(['id', 'license_plate', 'brand', 'model']),
            'drivers'      => Driver::where('active', true)->orderBy('name')->get(['id', 'name']),
            'vehicleTypes' => VehicleType::all(['id', 'requires_trailer']),
            'brStates'     => $this->brStates(),
        ]);
    }

    public function store(StoreFreightRequest $request, CreateFreightAction $action): RedirectResponse
    {
        $this->authorize('create', Freight::class);

        $freight = $action->handle($request->validated());

        return redirect()->route('freights.show', $freight)->with('success', 'Frete criado com sucesso.');
    }

    public function show(Freight $freight): Response
    {
        $this->authorize('view', $freight);

        $freight->load([
            'client', 'vehicle.vehicleType', 'trailer', 'driver',
            'fixedRate', 'perKmRate',
            'statusHistory.user',
        ]);

        // Pre-fill toll for fixed-rate finish modal
        $tollDefault = null;
        if ($freight->pricing_model === 'fixed' && $freight->fixed_rate_id) {
            $tollDefault = \App\Modules\Commercial\Models\FixedFreightRatePrice::where('fixed_freight_rate_id', $freight->fixed_rate_id)
                ->where('vehicle_type_id', $freight->vehicle->vehicle_type_id)
                ->value('tolls');
        }

        return Inertia::render('Operations/Show', [
            'freight'      => $freight,
            'tollDefault'  => $tollDefault,
            'estimatedLiters' => $freight->estimatedLiters(),
        ]);
    }

    public function transition(TransitionFreightRequest $request, Freight $freight, TransitionFreightAction $action): RedirectResponse
    {
        $this->authorize('transition', $freight);

        $action->handle($freight, $request->validated());

        return back()->with('success', 'Status atualizado.');
    }

    /** @return array<string, string> */
    private function brStates(): array
    {
        return [
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
            'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
            'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
            'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
            'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',
        ];
    }
}
```

- [ ] **Step 2: Create FreightRatesController (JSON endpoint for wizard step 2)**

```php
<?php
// app/Modules/Operations/Http/Controllers/FreightRatesController.php

namespace App\Modules\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\PerKmFreightRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FreightRatesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clientId = $request->query('client_id');
        $pricingModel = $request->query('pricing_model');

        if ($pricingModel === 'fixed') {
            $tables = ClientFreightTable::with('fixedRates')
                ->where('client_id', $clientId)
                ->where('pricing_model', 'fixed')
                ->where('active', true)
                ->get(['id', 'name', 'client_id']);

            return response()->json(['tables' => $tables]);
        }

        if ($pricingModel === 'per_km') {
            $rates = PerKmFreightRate::where('client_id', $clientId)
                ->get(['id', 'client_id', 'state']);

            return response()->json(['rates' => $rates]);
        }

        return response()->json(['tables' => [], 'rates' => []]);
    }
}
```

- [ ] **Step 3: Add routes to web.php**

```php
use App\Modules\Operations\Http\Controllers\FreightController;
use App\Modules\Operations\Http\Controllers\FreightRatesController;

// Inside the auth+tenant middleware group:
Route::resource('freights', FreightController::class)->only(['index', 'create', 'store', 'show']);
Route::post('freights/{freight}/transition', [FreightController::class, 'transition'])->name('freights.transition');
Route::get('freight-rates', FreightRatesController::class . '@index')->name('freight-rates.index');
```

- [ ] **Step 4: Run controller and transition tests**

```bash
php artisan test --filter "FreightControllerTest|FreightStateTransitionTest|FreightPolicyTest"
```

Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Operations/Http/Controllers/ routes/web.php
git commit -m "feat(operations): add FreightController, FreightRatesController, and routes"
```

---

## Task 10: PostgreSQL Trailer Enforcement Trigger

**Files:**
- Create: `database/migrations/rls/2026_04_20_000004_freight_trailer_trigger.php`

- [ ] **Step 1: Write failing test**

Add to `FreightControllerTest.php`:

```php
public function test_db_trigger_blocks_freight_without_trailer_when_required(): void
{
    $user = $this->makeUserWithRole('Operator');
    $type = VehicleType::factory()->create(['requires_trailer' => true]);
    $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id, 'vehicle_type_id' => $type->id, 'kind' => 'vehicle']);

    $this->expectException(\Illuminate\Database\QueryException::class);

    // Bypass action layer and insert directly
    \DB::table('freights')->insert([
        'company_id'    => $user->company_id,
        'client_id'     => Client::factory()->create(['company_id' => $user->company_id])->id,
        'vehicle_id'    => $vehicle->id,
        'trailer_id'    => null,
        'pricing_model' => 'fixed',
        'status'        => 'to_start',
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);
}
```

- [ ] **Step 2: Create trigger migration**

```php
<?php
// database/migrations/rls/2026_04_20_000004_freight_trailer_trigger.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION check_freight_trailer()
            RETURNS TRIGGER AS $$
            DECLARE
                needs_trailer BOOLEAN;
            BEGIN
                SELECT vt.requires_trailer INTO needs_trailer
                FROM vehicles v
                JOIN vehicle_types vt ON vt.id = v.vehicle_type_id
                WHERE v.id = NEW.vehicle_id;

                IF needs_trailer AND NEW.trailer_id IS NULL THEN
                    RAISE EXCEPTION 'Trailer obrigatório para este tipo de veículo.';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS enforce_freight_trailer ON freights;

            CREATE TRIGGER enforce_freight_trailer
            BEFORE INSERT OR UPDATE ON freights
            FOR EACH ROW EXECUTE FUNCTION check_freight_trailer();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS enforce_freight_trailer ON freights;
            DROP FUNCTION IF EXISTS check_freight_trailer();
        SQL);
    }
};
```

- [ ] **Step 3: Run migration and test**

```bash
php artisan migrate
php artisan test --filter test_db_trigger_blocks_freight_without_trailer_when_required
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/rls/
git commit -m "feat(operations): add PostgreSQL trigger for trailer enforcement"
```

---

## Task 11: Vue — Operations/Index.vue

**Files:**
- Create: `resources/js/Pages/Operations/Index.vue`

- [ ] **Step 1: Create Index.vue**

```vue
<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

const STATUS_LABELS = {
    to_start: 'A Iniciar',
    in_route: 'Em Rota',
    finished: 'Finalizado',
    awaiting_payment: 'Aguard. Pagamento',
    completed: 'Concluído',
}

const STATUS_COLORS = {
    to_start: 'bg-gray-100 text-gray-700',
    in_route: 'bg-blue-100 text-blue-700',
    finished: 'bg-yellow-100 text-yellow-700',
    awaiting_payment: 'bg-orange-100 text-orange-700',
    completed: 'bg-green-100 text-green-700',
}

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        freights: Object,
        filters: Object,
    },

    data() {
        return {
            search: this.filters?.search ?? '',
            status: this.filters?.status ?? '',
            statusLabels: STATUS_LABELS,
            statusColors: STATUS_COLORS,
        }
    },

    methods: {
        applyFilters() {
            router.get('/freights', { search: this.search, status: this.status }, {
                preserveState: true,
                replace: true,
            })
        },
        pricingLabel(model) {
            return model === 'fixed' ? 'Fixo' : 'Por Km'
        },
    },
}
</script>

<template>
    <Head title="Fretes" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Fretes</h1>
                <Link
                    href="/freights/create"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Novo Frete
                </Link>
            </div>
        </template>

        <div class="mb-5 flex flex-wrap gap-3">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                </svg>
                <input v-model="search" type="text" placeholder="Buscar cliente..." @input="applyFilters"
                    class="rounded-lg border border-gray-300 py-2 pl-9 pr-4 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            </div>
            <select v-model="status" @change="applyFilters"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="">Todos os status</option>
                <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
            </select>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Veículo</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Motorista</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Valor</th>
                        <th class="px-6 py-3.5" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-if="freights.data.length === 0">
                        <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400">Nenhum frete encontrado.</td>
                    </tr>
                    <tr v-for="freight in freights.data" :key="freight.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-sm text-gray-900">{{ freight.client?.name }}</td>
                        <td class="px-6 py-4 text-sm font-mono text-gray-700">{{ freight.vehicle?.license_plate }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ freight.driver?.name ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ pricingLabel(freight.pricing_model) }}</td>
                        <td class="px-6 py-4">
                            <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusColors[freight.status]]">
                                {{ statusLabels[freight.status] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            {{ freight.freight_value ? `R$ ${Number(freight.freight_value).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` : '—' }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <Link :href="`/freights/${freight.id}`" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Ver</Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="freights.last_page > 1" class="border-t border-gray-100 px-6 py-4 flex items-center justify-between text-sm text-gray-500">
                <span>{{ freights.total }} fretes</span>
                <div class="flex gap-2">
                    <Link v-if="freights.prev_page_url" :href="freights.prev_page_url" class="rounded px-3 py-1 hover:bg-gray-100">Anterior</Link>
                    <Link v-if="freights.next_page_url" :href="freights.next_page_url" class="rounded px-3 py-1 hover:bg-gray-100">Próximo</Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 2: Run all tests**

```bash
php artisan test
```

Expected: all pass.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Operations/Index.vue
git commit -m "feat(operations): add Freights index page"
```

---

## Task 12: Vue — Operations/Create.vue (4-step wizard)

**Files:**
- Create: `resources/js/Pages/Operations/Create.vue`

- [ ] **Step 1: Create Create.vue**

```vue
<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        clients: Array,
        vehicles: Array,
        trailers: Array,
        drivers: Array,
        vehicleTypes: Array,
        brStates: Object,
    },

    setup() {
        const form = useForm({
            client_id: '',
            pricing_model: 'fixed',
            origin: '',
            destination: '',
            fixed_rate_id: '',
            per_km_rate_id: '',
            client_freight_table_id: '',
            per_km_state: '',
            vehicle_id: '',
            trailer_id: '',
            driver_id: '',
        })
        return { form }
    },

    data() {
        return {
            step: 1,
            freightTables: [],
            fixedRates: [],
            perKmRates: [],
            loadingRates: false,
            rateError: '',
        }
    },

    computed: {
        selectedVehicle() {
            return this.vehicles.find(v => v.id === this.form.vehicle_id) ?? null
        },
        requiresTrailer() {
            if (!this.selectedVehicle) return false
            const type = this.vehicleTypes.find(t => t.id === this.selectedVehicle.vehicle_type_id)
            return type?.requires_trailer ?? false
        },
        canAdvanceStep1() {
            if (!this.form.client_id || !this.form.pricing_model) return false
            if (this.form.pricing_model === 'per_km') {
                return this.form.origin.trim() !== '' && this.form.destination.trim() !== ''
            }
            return true
        },
        canAdvanceStep2() {
            if (this.form.pricing_model === 'fixed') return !!this.form.fixed_rate_id
            return !!this.form.per_km_rate_id
        },
        canAdvanceStep3() {
            if (!this.form.vehicle_id || !this.form.driver_id) return false
            if (this.requiresTrailer && !this.form.trailer_id) return false
            return true
        },
        reviewClient() {
            return this.clients.find(c => c.id === this.form.client_id)
        },
        reviewVehicle() {
            return this.selectedVehicle
        },
        reviewDriver() {
            return this.drivers.find(d => d.id === this.form.driver_id)
        },
        reviewTrailer() {
            return this.trailers.find(t => t.id === this.form.trailer_id)
        },
    },

    watch: {
        'form.client_id': 'loadRates',
        'form.pricing_model': 'loadRates',
        'form.client_freight_table_id'(tableId) {
            this.fixedRates = this.freightTables.find(t => t.id === tableId)?.fixed_rates ?? []
            this.form.fixed_rate_id = ''
        },
        'form.per_km_state'(state) {
            const match = this.perKmRates.find(r => r.state === state)
            this.form.per_km_rate_id = match?.id ?? ''
            this.rateError = match ? '' : 'Nenhuma tarifa cadastrada para este estado.'
        },
    },

    methods: {
        async loadRates() {
            if (!this.form.client_id || !this.form.pricing_model) return
            this.loadingRates = true
            this.rateError = ''
            this.freightTables = []
            this.fixedRates = []
            this.perKmRates = []
            this.form.fixed_rate_id = ''
            this.form.per_km_rate_id = ''
            this.form.client_freight_table_id = ''
            this.form.per_km_state = ''

            try {
                const { data } = await axios.get('/freight-rates', {
                    params: { client_id: this.form.client_id, pricing_model: this.form.pricing_model },
                })
                this.freightTables = data.tables ?? []
                this.perKmRates = data.rates ?? []
            } finally {
                this.loadingRates = false
            }
        },

        submit() {
            this.form.post('/freights')
        },

        formatCurrency(val) {
            if (!val) return '—'
            return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },
    },
}
</script>

<template>
    <Head title="Novo Frete" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/freights" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900">Novo Frete</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <!-- Step indicator -->
            <div class="mb-6 flex items-center gap-2">
                <template v-for="n in 4" :key="n">
                    <div :class="['flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium',
                        step === n ? 'bg-indigo-600 text-white' :
                        step > n ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-400']">
                        {{ n }}
                    </div>
                    <div v-if="n < 4" :class="['h-0.5 flex-1', step > n ? 'bg-indigo-300' : 'bg-gray-200']" />
                </template>
            </div>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">

                <!-- Step 1: Frete -->
                <div v-if="step === 1">
                    <div class="px-6 py-5 border-b border-gray-100">
                        <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Passo 1 — Frete</h2>
                    </div>
                    <div class="px-6 py-5 space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Cliente</label>
                            <select v-model="form.client_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">Selecione o cliente...</option>
                                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="form.errors.client_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.client_id }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de Tarifa</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="form.pricing_model" value="fixed" class="text-indigo-600" />
                                    <span class="text-sm text-gray-700">Fixo</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="form.pricing_model" value="per_km" class="text-indigo-600" />
                                    <span class="text-sm text-gray-700">Por Km</span>
                                </label>
                            </div>
                        </div>

                        <template v-if="form.pricing_model === 'per_km'">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Origem</label>
                                <input v-model="form.origin" type="text" placeholder="Cidade de origem"
                                    class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                <p v-if="form.errors.origin" class="mt-1.5 text-xs text-red-600">{{ form.errors.origin }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Destino</label>
                                <input v-model="form.destination" type="text" placeholder="Cidade de destino"
                                    class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                <p v-if="form.errors.destination" class="mt-1.5 text-xs text-red-600">{{ form.errors.destination }}</p>
                            </div>
                        </template>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                        <button :disabled="!canAdvanceStep1" @click="step = 2"
                            class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            Próximo
                        </button>
                    </div>
                </div>

                <!-- Step 2: Tarifa -->
                <div v-if="step === 2">
                    <div class="px-6 py-5 border-b border-gray-100">
                        <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Passo 2 — Tarifa</h2>
                    </div>
                    <div class="px-6 py-5 space-y-5">
                        <div v-if="loadingRates" class="text-sm text-gray-400">Carregando tarifas...</div>

                        <!-- Fixed rate selection -->
                        <template v-if="form.pricing_model === 'fixed' && !loadingRates">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tabela de Fretes</label>
                                <select v-model="form.client_freight_table_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Selecione a tabela...</option>
                                    <option v-for="t in freightTables" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                                <p v-if="freightTables.length === 0" class="mt-1.5 text-xs text-amber-600">Nenhuma tabela fixa cadastrada para este cliente.</p>
                            </div>
                            <div v-if="form.client_freight_table_id">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Rota</label>
                                <select v-model="form.fixed_rate_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Selecione a rota...</option>
                                    <option v-for="r in fixedRates" :key="r.id" :value="r.id">{{ r.name }} <template v-if="r.avg_km">({{ r.avg_km }} km)</template></option>
                                </select>
                                <p v-if="form.errors.fixed_rate_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.fixed_rate_id }}</p>
                            </div>
                        </template>

                        <!-- Per-km rate selection -->
                        <template v-if="form.pricing_model === 'per_km' && !loadingRates">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Estado</label>
                                <select v-model="form.per_km_state" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Selecione o estado...</option>
                                    <option v-for="(label, code) in brStates" :key="code" :value="code">{{ code }} — {{ label }}</option>
                                </select>
                                <p v-if="rateError" class="mt-1.5 text-xs text-red-600">{{ rateError }}</p>
                                <p v-if="form.errors.per_km_rate_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.per_km_rate_id }}</p>
                            </div>
                        </template>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-between">
                        <button @click="step = 1" class="rounded-lg border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Voltar</button>
                        <button :disabled="!canAdvanceStep2" @click="step = 3"
                            class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            Próximo
                        </button>
                    </div>
                </div>

                <!-- Step 3: Equipe -->
                <div v-if="step === 3">
                    <div class="px-6 py-5 border-b border-gray-100">
                        <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Passo 3 — Equipe</h2>
                    </div>
                    <div class="px-6 py-5 space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Veículo</label>
                            <select v-model="form.vehicle_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">Selecione o veículo...</option>
                                <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.license_plate }} — {{ v.brand }} {{ v.model }}</option>
                            </select>
                            <p v-if="form.errors.vehicle_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.vehicle_id }}</p>
                        </div>

                        <div v-if="requiresTrailer">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Reboque <span class="text-red-500">*</span></label>
                            <select v-model="form.trailer_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">Selecione o reboque...</option>
                                <option v-for="t in trailers" :key="t.id" :value="t.id">{{ t.license_plate }} — {{ t.brand }} {{ t.model }}</option>
                            </select>
                            <p v-if="form.errors.trailer_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.trailer_id }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Motorista</label>
                            <select v-model="form.driver_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">Selecione o motorista...</option>
                                <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                            <p v-if="form.errors.driver_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.driver_id }}</p>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-between">
                        <button @click="step = 2" class="rounded-lg border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Voltar</button>
                        <button :disabled="!canAdvanceStep3" @click="step = 4"
                            class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            Próximo
                        </button>
                    </div>
                </div>

                <!-- Step 4: Revisão -->
                <div v-if="step === 4">
                    <div class="px-6 py-5 border-b border-gray-100">
                        <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Passo 4 — Revisão</h2>
                    </div>
                    <div class="px-6 py-5 space-y-3 text-sm">
                        <div class="grid grid-cols-2 gap-y-3">
                            <span class="text-gray-500">Cliente</span><span class="font-medium text-gray-900">{{ reviewClient?.name }}</span>
                            <span class="text-gray-500">Tipo de tarifa</span><span class="font-medium text-gray-900">{{ form.pricing_model === 'fixed' ? 'Fixo' : 'Por Km' }}</span>
                            <template v-if="form.pricing_model === 'per_km'">
                                <span class="text-gray-500">Origem</span><span class="font-medium text-gray-900">{{ form.origin }}</span>
                                <span class="text-gray-500">Destino</span><span class="font-medium text-gray-900">{{ form.destination }}</span>
                            </template>
                            <span class="text-gray-500">Veículo</span><span class="font-mono font-medium text-gray-900">{{ reviewVehicle?.license_plate }}</span>
                            <template v-if="reviewTrailer">
                                <span class="text-gray-500">Reboque</span><span class="font-mono font-medium text-gray-900">{{ reviewTrailer.license_plate }}</span>
                            </template>
                            <span class="text-gray-500">Motorista</span><span class="font-medium text-gray-900">{{ reviewDriver?.name }}</span>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-between">
                        <button @click="step = 3" class="rounded-lg border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Voltar</button>
                        <button :disabled="form.processing" @click="submit"
                            class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60 transition-colors">
                            {{ form.processing ? 'Criando...' : 'Criar Frete' }}
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/Operations/Create.vue
git commit -m "feat(operations): add freight creation wizard (4 steps)"
```

---

## Task 13: Vue — Operations/Show.vue

**Files:**
- Create: `resources/js/Pages/Operations/Show.vue`

- [ ] **Step 1: Create Show.vue**

```vue
<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { vMaska } from 'maska/vue'

const STATUS_LABELS = {
    to_start: 'A Iniciar',
    in_route: 'Em Rota',
    finished: 'Finalizado',
    awaiting_payment: 'Aguardando Pagamento',
    completed: 'Concluído',
}

const STATUS_COLORS = {
    to_start: 'bg-gray-100 text-gray-700',
    in_route: 'bg-blue-100 text-blue-700',
    finished: 'bg-yellow-100 text-yellow-700',
    awaiting_payment: 'bg-orange-100 text-orange-700',
    completed: 'bg-green-100 text-green-700',
}

export default {
    components: { AuthenticatedLayout, Head, Link },
    directives: { maska: vMaska },

    props: {
        freight: Object,
        tollDefault: { type: [Number, String], default: null },
        estimatedLiters: { type: [Number, String], default: null },
    },

    setup(props) {
        const finishForm = useForm({
            transition: 'to_finished',
            distance_km: '',
            toll: props.tollDefault ? String(props.tollDefault) : '',
            fuel_price_per_liter: '',
        })
        return { finishForm }
    },

    data() {
        return {
            showFinishModal: false,
            statusLabels: STATUS_LABELS,
            statusColors: STATUS_COLORS,
        }
    },

    computed: {
        estimatedFuelCost() {
            const liters = this.estimatedLitersComputed
            const price = parseFloat(this.finishForm.fuel_price_per_liter)
            if (!liters || !price) return null
            return (liters * price).toFixed(2)
        },
        estimatedLitersComputed() {
            const km = parseFloat(this.finishForm.distance_km)
            const consumo = this.freight.vehicle?.consumo_medio
            if (!km || !consumo) return this.estimatedLiters
            return (km / parseFloat(consumo)).toFixed(2)
        },
    },

    methods: {
        transition(transitionKey) {
            this.$inertia.post(`/freights/${this.freight.id}/transition`, { transition: transitionKey })
        },
        submitFinish() {
            this.finishForm.post(`/freights/${this.freight.id}/transition`, {
                onSuccess: () => { this.showFinishModal = false },
            })
        },
        formatCurrency(val) {
            if (val === null || val === undefined) return '—'
            return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },
        formatDate(val) {
            if (!val) return '—'
            return new Date(val).toLocaleString('pt-BR')
        },
    },
}
</script>

<template>
    <Head :title="`Frete #${freight.id}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link href="/freights" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h1 class="text-xl font-semibold text-gray-900">Frete #{{ freight.id }}</h1>
                    <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusColors[freight.status]]">
                        {{ statusLabels[freight.status] }}
                    </span>
                </div>

                <!-- Transition buttons -->
                <div class="flex gap-2">
                    <button v-if="freight.status === 'to_start'" @click="transition('to_in_route')"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-500 transition-colors">
                        Iniciar Frete
                    </button>
                    <button v-if="freight.status === 'in_route'" @click="showFinishModal = true"
                        class="rounded-lg bg-yellow-500 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-yellow-400 transition-colors">
                        Finalizar Frete
                    </button>
                    <button v-if="freight.status === 'finished'" @click="transition('to_awaiting_payment')"
                        class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-orange-400 transition-colors">
                        Enviar para Pagamento
                    </button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6">

            <!-- Main info card -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Informações</h2>
                </div>
                <dl class="divide-y divide-gray-100">
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Cliente</dt>
                        <dd class="col-span-2 text-gray-900">{{ freight.client?.name }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Tarifa</dt>
                        <dd class="col-span-2 text-gray-900">{{ freight.pricing_model === 'fixed' ? 'Fixo' : 'Por Km' }}</dd>
                    </div>
                    <template v-if="freight.origin">
                        <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                            <dt class="font-medium text-gray-500">Origem / Destino</dt>
                            <dd class="col-span-2 text-gray-900">{{ freight.origin }} → {{ freight.destination }}</dd>
                        </div>
                    </template>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Veículo</dt>
                        <dd class="col-span-2 font-mono text-gray-900">{{ freight.vehicle?.license_plate }} — {{ freight.vehicle?.brand }} {{ freight.vehicle?.model }}</dd>
                    </div>
                    <div v-if="freight.trailer" class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Reboque</dt>
                        <dd class="col-span-2 font-mono text-gray-900">{{ freight.trailer.license_plate }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Motorista</dt>
                        <dd class="col-span-2 text-gray-900">{{ freight.driver?.name ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Costs card (shown after finished) -->
            <div v-if="freight.finished_at" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Custos</h2>
                </div>
                <dl class="divide-y divide-gray-100">
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Km percorrido</dt>
                        <dd class="col-span-2 text-gray-900">{{ freight.distance_km ? `${freight.distance_km} km` : '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Pedágio</dt>
                        <dd class="col-span-2 text-gray-900">{{ formatCurrency(freight.toll) }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Preço combustível (L)</dt>
                        <dd class="col-span-2 text-gray-900">{{ freight.fuel_price_per_liter ? formatCurrency(freight.fuel_price_per_liter) : '—' }}</dd>
                    </div>
                    <div v-if="freight.freight_value" class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Valor do frete</dt>
                        <dd class="col-span-2 font-semibold text-gray-900">{{ formatCurrency(freight.freight_value) }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Status history -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Histórico</h2>
                </div>
                <ul class="divide-y divide-gray-100">
                    <li v-for="h in freight.status_history" :key="h.id" class="flex items-center gap-4 px-6 py-3 text-sm">
                        <div class="flex-1">
                            <span class="text-gray-500">{{ h.from_status ? statusLabels[h.from_status] + ' →' : '' }}</span>
                            <span class="ml-1 font-medium text-gray-900">{{ statusLabels[h.to_status] }}</span>
                            <span class="ml-2 text-gray-400">por {{ h.user?.name ?? 'sistema' }}</span>
                        </div>
                        <span class="text-gray-400">{{ formatDate(h.occurred_at) }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Finish modal -->
        <div v-if="showFinishModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Finalizar Frete</h3>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div v-if="freight.pricing_model === 'per_km'">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Km percorrido <span class="text-red-500">*</span></label>
                        <input v-model="finishForm.distance_km" type="number" step="0.1" min="1" placeholder="Ex: 480"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="finishForm.errors.distance_km" class="mt-1.5 text-xs text-red-600">{{ finishForm.errors.distance_km }}</p>
                    </div>
                    <div v-else>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Km percorrido</label>
                        <input v-model="finishForm.distance_km" type="number" step="0.1" min="1" placeholder="Opcional"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Pedágio {{ freight.pricing_model === 'per_km' ? '*' : '' }}
                            <span v-if="freight.pricing_model === 'fixed' && tollDefault" class="text-xs text-gray-400">(pré-preenchido da tarifa)</span>
                        </label>
                        <input
                            v-model="finishForm.toll"
                            v-maska="{ mask: ['#,##', '##,##', '###,##', '####,##', '#####,##'], tokens: { '#': { pattern: /[0-9]/ } } }"
                            type="text" inputmode="numeric" placeholder="0,00"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="finishForm.errors.toll" class="mt-1.5 text-xs text-red-600">{{ finishForm.errors.toll }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Preço do combustível (R$/L)</label>
                        <input
                            v-model="finishForm.fuel_price_per_liter"
                            v-maska="{ mask: ['#,####', '##,####'], tokens: { '#': { pattern: /[0-9]/ } } }"
                            type="text" inputmode="numeric" placeholder="0,0000"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>

                    <!-- Computed estimates -->
                    <div v-if="estimatedLitersComputed" class="rounded-lg bg-blue-50 px-4 py-3 text-sm space-y-1">
                        <div class="flex justify-between text-blue-800">
                            <span>Litros estimados</span>
                            <span class="font-medium">{{ estimatedLitersComputed }} L</span>
                        </div>
                        <div v-if="estimatedFuelCost" class="flex justify-between text-blue-800">
                            <span>Custo combustível estimado</span>
                            <span class="font-medium">{{ formatCurrency(estimatedFuelCost) }}</span>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                    <button @click="showFinishModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button :disabled="finishForm.processing" @click="submitFinish"
                        class="rounded-lg bg-yellow-500 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-400 disabled:opacity-60">
                        {{ finishForm.processing ? 'Salvando...' : 'Confirmar' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/Operations/Show.vue
git commit -m "feat(operations): add freight show page with transition buttons and finish modal"
```

---

## Task 14: Navigation and RLS

**Files:**
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`
- Create: `database/migrations/rls/2026_04_20_000005_freight_rls_policies.php`

- [ ] **Step 1: Add Fretes to sidebar navigation**

In `resources/js/Layouts/AuthenticatedLayout.vue`, find the `navItems` array and add:

```javascript
{ label: 'Fretes', route: 'freights.index', match: 'freights.*', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />' },
```

- [ ] **Step 2: Add RLS policies for freights**

```php
<?php
// database/migrations/rls/2026_04_20_000005_freight_rls_policies.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE freights ENABLE ROW LEVEL SECURITY;
            ALTER TABLE freight_status_history ENABLE ROW LEVEL SECURITY;

            CREATE POLICY freights_company_isolation ON freights
                USING (company_id = current_setting('app.current_company_id', true)::bigint);

            CREATE POLICY freight_history_company_isolation ON freight_status_history
                USING (freight_id IN (
                    SELECT id FROM freights
                    WHERE company_id = current_setting('app.current_company_id', true)::bigint
                ));
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP POLICY IF EXISTS freights_company_isolation ON freights;
            DROP POLICY IF EXISTS freight_history_company_isolation ON freight_status_history;
            ALTER TABLE freights DISABLE ROW LEVEL SECURITY;
            ALTER TABLE freight_status_history DISABLE ROW LEVEL SECURITY;
        SQL);
    }
};
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 4: Run all tests**

```bash
php artisan test
```

Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Layouts/AuthenticatedLayout.vue database/migrations/rls/
git commit -m "feat(operations): add Fretes nav item and RLS policies for freights"
```

---

## Task 15: Factories for Commercial Models (needed by tests)

The FreightControllerTest and FreightStateTransitionTest require factories for `ClientFreightTable`, `FixedFreightRate`, and `FixedFreightRatePrice`. Check if they exist:

```bash
ls database/factories/Commercial/
```

If `ClientFreightTableFactory.php`, `FixedFreightRateFactory.php`, and `FixedFreightRatePriceFactory.php` are missing, create them:

- [ ] **Step 1: Create missing factories only if absent**

`ClientFreightTableFactory.php`:
```php
<?php
namespace Database\Factories\Commercial;
use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<ClientFreightTable> */
class ClientFreightTableFactory extends Factory
{
    protected $model = ClientFreightTable::class;
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'client_id' => Client::factory(),
            'name' => $this->faker->words(2, true),
            'pricing_model' => 'fixed',
            'active' => true,
        ];
    }
}
```

`FixedFreightRateFactory.php`:
```php
<?php
namespace Database\Factories\Commercial;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<FixedFreightRate> */
class FixedFreightRateFactory extends Factory
{
    protected $model = FixedFreightRate::class;
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'client_freight_table_id' => ClientFreightTable::factory(),
            'name' => $this->faker->city() . ' ' . $this->faker->randomNumber(1),
            'avg_km' => $this->faker->numberBetween(50, 800),
        ];
    }
}
```

`FixedFreightRatePriceFactory.php`:
```php
<?php
namespace Database\Factories\Commercial;
use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Commercial\Models\FixedFreightRatePrice;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<FixedFreightRatePrice> */
class FixedFreightRatePriceFactory extends Factory
{
    protected $model = FixedFreightRatePrice::class;
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'fixed_freight_rate_id' => FixedFreightRate::factory(),
            'vehicle_type_id' => VehicleType::factory(),
            'price' => $this->faker->randomFloat(2, 200, 5000),
            'tolls' => $this->faker->randomFloat(2, 0, 500),
            'fuel_cost' => $this->faker->randomFloat(2, 0, 800),
        ];
    }
}
```

Also add `HasFactory` + factory references to those models if missing (check each model).

- [ ] **Step 2: Run all tests**

```bash
php artisan test
```

Expected: all pass.

- [ ] **Step 3: Commit**

```bash
git add database/factories/Commercial/
git commit -m "test(commercial): add missing factories for freight rate price models"
```

---

## Task 16: Final Verification

- [ ] **Step 1: Run full test suite**

```bash
php artisan test
```

Expected: all tests pass, 0 failures.

- [ ] **Step 2: Run static analysis**

```bash
./vendor/bin/pint --test
```

Expected: no style violations.

- [ ] **Step 3: Commit if any lint fixes needed**

```bash
./vendor/bin/pint
git add -p
git commit -m "style: apply pint formatting for Epic 4"
```

---

## Toll and Fuel Mask Note

The finish modal uses `v-maska` for `toll` and `fuel_price_per_liter`. Before submitting, strip non-numeric characters in `submitFinish()`:

```javascript
submitFinish() {
    const cleanedToll = (this.finishForm.toll || '').replace(',', '.').replace(/[^\d.]/g, '')
    const cleanedFuel = (this.finishForm.fuel_price_per_liter || '').replace(',', '.').replace(/[^\d.]/g, '')
    this.finishForm.toll = cleanedToll || null
    this.finishForm.fuel_price_per_liter = cleanedFuel || null
    this.finishForm.post(`/freights/${this.freight.id}/transition`, {
        onSuccess: () => { this.showFinishModal = false },
    })
},
```

Also clean in `TransitionFreightRequest.prepareForValidation()`:

```php
protected function prepareForValidation(): void
{
    if ($this->toll !== null) {
        $this->merge(['toll' => str_replace(',', '.', preg_replace('/[^\d,]/', '', $this->toll))]);
    }
    if ($this->fuel_price_per_liter !== null) {
        $this->merge(['fuel_price_per_liter' => str_replace(',', '.', preg_replace('/[^\d,]/', '', $this->fuel_price_per_liter))]);
    }
}
```
