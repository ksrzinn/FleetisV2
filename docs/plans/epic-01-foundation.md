# Epic 1 — Foundation: Step-Level TDD Implementation Plan

## Goal

Bootstrap the Fleetis v2 codebase with Laravel 11 + Breeze (Inertia/Vue3), PostgreSQL 16, a homebrewed multi-tenant scoping trait backed by PostgreSQL Row-Level Security, Spatie permission v6 with teams, a signup flow that atomically creates a company plus its admin user, and a CI pipeline running tests, style, static analysis, and JS lint. Ship a proven cross-tenant leakage test harness so every subsequent epic can assert its own tenant isolation in one line.

## Architecture

Three defensive layers for tenancy, stacked cheapest-first:

1. **Application layer.** `BelongsToCompany` trait installs an Eloquent global scope and a `creating` event that auto-fills `company_id` from `auth()->user()->company_id`.
2. **Request layer.** `EnsureTenantContext` middleware runs after `auth` on every authenticated web/API request. It asserts `auth()->user()->company_id` is non-null and executes `SET LOCAL app.current_company_id = :id` on the current DB connection so RLS can evaluate.
3. **Database layer.** Every tenant-scoped table has RLS enabled with a policy `USING (company_id = current_setting('app.current_company_id')::bigint)`. This is the last-line defense against raw-SQL scope bypass, bad joins, and future N+1 hydration bugs.

Spatie's `teams` feature maps `team_id → company_id`. Admin/Operator/Financial roles are seeded **per company** at signup time, inside the signup transaction.

Signup replaces Breeze's `register` route with a single `CompanyRegisterController` that creates the Company, the first User, the three Roles scoped to that company's team, and attaches `Admin` to the user — all in one transaction. Login, password reset, and email verification stay Breeze-default.

## Tech Stack

- PHP 8.3, Laravel 11, Composer 2
- PostgreSQL 16 (RLS requires ≥ 9.5, we're well past)
- Node 20, npm, Vite, TailwindCSS 3, Vue 3 (Options API), Inertia.js
- Packages: `laravel/breeze` ^2, `spatie/laravel-permission` ^6, `spatie/laravel-data` ^4
- Dev: `larastan/larastan` ^3, `laravel/pint` (bundled), PHPUnit 11 (bundled), ESLint 9
- CI: GitHub Actions with a `postgres:16` service container

---

## Context

Working directory `/var/www/html/projects/fleetis-v2` already contains `.git/`, `docs/`, and `.gitignore`. Everything else is missing. Laravel's project skeleton installer refuses non-empty targets, so we install into a sibling directory and merge the skeleton in, preserving `.git/` and `docs/`.

We front-load the RLS work and the cross-tenant leakage test harness because (a) retrofitting RLS after 20 tables exist is painful and (b) every future epic's "tenant leak" test will be a one-liner on top of what we build here. The cost is roughly 2–3 tasks of pure plumbing before any visible feature — acceptable given this is a multi-tenant finance SaaS where a leak is existential.

Why this plan skips things the MVP doc mentions for Epic 1:
- **ApexCharts.** Deferred to Epic 8. Installing it now just adds a bundle-size cost and a dependency to keep green with nothing to render.
- **Activity log.** Deferred to Epic 4 where the first auditable transitions exist.
- **Policy base class.** Included as a thin `TenantPolicy` superclass but not populated with rich helpers — we'll accrete helpers as concrete policies land in Epics 2–7. YAGNI.

Known RLS footguns the plan mitigates:
- Queued-job DB connection leak → the middleware uses `SET LOCAL` (transaction-scoped) and we wrap every request in a transaction via a bootstrap in `EnsureTenantContext`. Jobs will get their own middleware in a later epic; Epic 1 has no queued jobs.
- CLI/seeder bypass → the DB user used for migrations is the owner, and RLS is declared with `FORCE ROW LEVEL SECURITY` so even the owner must satisfy the policy when querying. Migrations run as a separate "migrator" role that is explicitly `BYPASSRLS`. We document this in the README task and script it in setup.
- Missing RLS on new tables → the harness test iterates `information_schema` for tenant-scoped tables and fails the build if any lacks a policy. This is the load-bearing guardrail.

---

## Tasks

### Task 1 — Install Laravel 11 into the existing working directory

**Files touched:** everything at the project root (new), plus preserves `.git/`, `docs/`, `.gitignore`.

**Steps:**

1. **Verify working directory state.**
   ```
   ls -la /var/www/html/projects/fleetis-v2
   ```
   Expect: `.git`, `.gitignore`, `docs` visible; no `composer.json`, no `artisan`.

2. **Install Laravel into a sibling directory.**
   ```
   cd /var/www/html/projects
   composer create-project --prefer-dist laravel/laravel:^11 fleetis-v2-install
   ```
   Expect: final line `Application key set successfully.` and exit code 0.

3. **Merge skeleton into working dir preserving `.git` and `docs`.**
   ```
   cd /var/www/html/projects/fleetis-v2-install
   rm -rf .git
   cp -R . /var/www/html/projects/fleetis-v2/
   cp .env.example /var/www/html/projects/fleetis-v2/.env.example
   cp .gitignore /var/www/html/projects/fleetis-v2/.gitignore.laravel
   cd /var/www/html/projects/fleetis-v2
   ```
   Merge the two `.gitignore` files by hand: keep any project-specific lines from the original, append Laravel's lines, delete `.gitignore.laravel`.

4. **Clean up install dir.**
   ```
   rm -rf /var/www/html/projects/fleetis-v2-install
   ```

5. **Run the failing "app boots" test.**
   ```
   cd /var/www/html/projects/fleetis-v2
   php artisan --version
   ```
   Expect: `Laravel Framework 11.x.y`. If it errors with missing vendor, run `composer install`.

6. **Run the bundled example test to prove install health.**
   ```
   php artisan test
   ```
   Expect: 2 passing tests (Breeze not yet scaffolded — these are the default `ExampleTest`s).

7. **Commit.**
   ```
   git add -A
   git commit -m "chore: install Laravel 11 skeleton"
   ```

---

### Task 2 — Configure PostgreSQL connection and create the database

**Files touched:** `.env`, `.env.example`, `config/database.php` (no-op verify).

**Steps:**

1. **Write the failing test** — a one-off DB smoke test. Create `tests/Feature/DatabaseConnectionTest.php`:
   ```php
   <?php

   namespace Tests\Feature;

   use Illuminate\Support\Facades\DB;
   use Tests\TestCase;

   class DatabaseConnectionTest extends TestCase
   {
       public function test_postgres_connection_works(): void
       {
           $driver = DB::connection()->getDriverName();
           $this->assertSame('pgsql', $driver);

           $result = DB::selectOne('select version() as v');
           $this->assertStringContainsString('PostgreSQL', $result->v);
       }
   }
   ```

2. **Run it, expect FAIL.**
   ```
   php artisan test --filter=DatabaseConnectionTest
   ```
   Expect: failure — driver is `sqlite` (Laravel 11 default).

3. **Edit `.env` and `.env.example`.** Replace the DB block in both:
   ```
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=fleetis_v2
   DB_USERNAME=fleetis
   DB_PASSWORD=fleetis
   ```
   In `.env` additionally keep `APP_ENV=local`, `APP_DEBUG=true`.

4. **Manual step (flag for user confirmation).** Before continuing, the user must run:
   ```
   sudo -u postgres psql -c "CREATE USER fleetis WITH PASSWORD 'fleetis';"
   sudo -u postgres psql -c "CREATE DATABASE fleetis_v2 OWNER fleetis;"
   sudo -u postgres psql -c "ALTER USER fleetis CREATEDB;"
   ```
   The `CREATEDB` grant is needed for PHPUnit's parallel DB creation in later epics. Confirm with: `psql -h 127.0.0.1 -U fleetis -d fleetis_v2 -c "select 1;"`.

5. **Run, expect PASS.**
   ```
   php artisan test --filter=DatabaseConnectionTest
   ```
   Expect: 1 passing test.

6. **Commit.**
   ```
   git add .env.example tests/Feature/DatabaseConnectionTest.php
   git commit -m "chore: switch default DB driver to pgsql and add connection smoke test"
   ```
   `.env` itself stays gitignored.

---

### Task 3 — Scaffold Breeze (Inertia + Vue 3, Options API)

**Files touched:** many new under `resources/js/`, `resources/views/`, `routes/`, `app/Http/Controllers/Auth/`, `package.json`, `vite.config.js`, `tailwind.config.js`.

**Steps:**

1. **Install Breeze dev dep.**
   ```
   composer require laravel/breeze:^2 --dev
   ```
   Expect: resolved successfully, no conflicts.

2. **Run the Breeze installer in Vue mode, no SSR, no TypeScript, no dark mode.**
   ```
   php artisan breeze:install vue --no-interaction
   ```
   Expect: files generated under `resources/js/Pages/Auth`, `resources/js/Layouts`, etc.

3. **Install JS deps and build.**
   ```
   npm install
   npm run build
   ```
   Expect: Vite prints asset manifest, no errors.

4. **Write the failing test — homepage renders Welcome component.** Update `tests/Feature/ExampleTest.php` or create `tests/Feature/Auth/RegistrationPageTest.php`:
   ```php
   <?php

   namespace Tests\Feature\Auth;

   use Tests\TestCase;

   class RegistrationPageTest extends TestCase
   {
       public function test_registration_screen_renders(): void
       {
           $response = $this->get('/register');
           $response->assertStatus(200);
       }
   }
   ```

5. **Migrate and run.**
   ```
   php artisan migrate
   php artisan test --filter=RegistrationPageTest
   ```
   Expect: PASS. `migrate` creates the stock `users`, `cache`, `jobs`, `password_reset_tokens`, `sessions` tables.

6. **Verify Options API convention.** Open `resources/js/Pages/Auth/Login.vue` — Breeze ships Composition API in v2. Convert the top of `Login.vue` to Options API as a reference example:
   ```vue
   <script>
   import GuestLayout from '@/Layouts/GuestLayout.vue'
   import InputError from '@/Components/InputError.vue'
   import InputLabel from '@/Components/InputLabel.vue'
   import PrimaryButton from '@/Components/PrimaryButton.vue'
   import TextInput from '@/Components/TextInput.vue'
   import { Head, Link, useForm } from '@inertiajs/vue3'

   export default {
     components: { GuestLayout, InputError, InputLabel, PrimaryButton, TextInput, Head, Link },
     props: {
       canResetPassword: Boolean,
       status: String,
     },
     data() {
       return {
         form: useForm({ email: '', password: '', remember: false }),
       }
     },
     methods: {
       submit() {
         this.form.post(route('login'), { onFinish: () => this.form.reset('password') })
       },
     },
   }
   </script>
   ```
   Leave the template block intact. We leave the remaining Breeze pages in their generated form for Task 6 to batch-convert — this one file is the reference.

7. **Commit.**
   ```
   git add -A
   git commit -m "feat(auth): scaffold Breeze Inertia+Vue3, convert Login.vue to Options API"
   ```

---

### Task 4 — Create `companies` table

**Files touched:** `database/migrations/xxxx_create_companies_table.php`, `app/Modules/Tenancy/Models/Company.php`, `tests/Feature/Tenancy/CompanyModelTest.php`.

**Steps:**

1. **Write the failing test** at `tests/Feature/Tenancy/CompanyModelTest.php`:
   ```php
   <?php

   namespace Tests\Feature\Tenancy;

   use App\Modules\Tenancy\Models\Company;
   use Illuminate\Foundation\Testing\RefreshDatabase;
   use Tests\TestCase;

   class CompanyModelTest extends TestCase
   {
       use RefreshDatabase;

       public function test_company_can_be_created_with_required_fields(): void
       {
           $company = Company::create([
               'name' => 'Transportadora Silva',
               'cnpj' => '12345678000190',
               'timezone' => 'America/Sao_Paulo',
               'status' => 'active',
           ]);

           $this->assertDatabaseHas('companies', ['name' => 'Transportadora Silva']);
           $this->assertSame('active', $company->status);
       }
   }
   ```

2. **Run it, expect FAIL.**
   ```
   php artisan test --filter=CompanyModelTest
   ```
   Expect: `Class "App\Modules\Tenancy\Models\Company" not found`.

3. **Create the migration.**
   ```
   php artisan make:migration create_companies_table
   ```
   Edit the generated file:
   ```php
   public function up(): void
   {
       Schema::create('companies', function (Blueprint $table) {
           $table->id();
           $table->string('name');
           $table->string('cnpj', 18)->unique();
           $table->string('timezone')->default('America/Sao_Paulo');
           $table->string('status')->default('active');
           $table->timestamps();
       });
   }

   public function down(): void
   {
       Schema::dropIfExists('companies');
   }
   ```

4. **Create the model** at `app/Modules/Tenancy/Models/Company.php`:
   ```php
   <?php

   namespace App\Modules\Tenancy\Models;

   use Illuminate\Database\Eloquent\Factories\HasFactory;
   use Illuminate\Database\Eloquent\Model;

   class Company extends Model
   {
       use HasFactory;

       protected $fillable = ['name', 'cnpj', 'timezone', 'status'];
   }
   ```
   Register module autoload by editing `composer.json` `autoload.psr-4`:
   ```json
   "App\\Modules\\": "app/Modules/"
   ```
   Run `composer dump-autoload`.

5. **Migrate.**
   ```
   php artisan migrate
   ```
   Expect: `Migrated: xxxx_create_companies_table`.

6. **Run, expect PASS.**
   ```
   php artisan test --filter=CompanyModelTest
   ```
   Expect: 1 passing test.

7. **Commit.**
   ```
   git add -A
   git commit -m "feat(tenancy): add companies table and Company model"
   ```

---

### Task 5 — Extend `users` table with `company_id` and belongsTo relation

**Files touched:** new migration `xxxx_add_company_id_to_users_table.php`, `app/Models/User.php`.

**Steps:**

1. **Write the failing test** in `tests/Feature/Tenancy/UserBelongsToCompanyTest.php`:
   ```php
   <?php

   namespace Tests\Feature\Tenancy;

   use App\Models\User;
   use App\Modules\Tenancy\Models\Company;
   use Illuminate\Foundation\Testing\RefreshDatabase;
   use Tests\TestCase;

   class UserBelongsToCompanyTest extends TestCase
   {
       use RefreshDatabase;

       public function test_user_belongs_to_a_company(): void
       {
           $company = Company::create([
               'name' => 'Acme', 'cnpj' => '11111111000111',
               'timezone' => 'UTC', 'status' => 'active',
           ]);
           $user = User::factory()->create(['company_id' => $company->id]);

           $this->assertInstanceOf(Company::class, $user->company);
           $this->assertSame($company->id, $user->company->id);
       }
   }
   ```

2. **Run it, expect FAIL.**
   ```
   php artisan test --filter=UserBelongsToCompanyTest
   ```
   Expect: SQL error — `column "company_id" does not exist`.

3. **Generate and edit the migration.**
   ```
   php artisan make:migration add_company_id_to_users_table --table=users
   ```
   ```php
   public function up(): void
   {
       Schema::table('users', function (Blueprint $table) {
           $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
           $table->dropUnique('users_email_unique');
           $table->unique(['company_id', 'email']);
       });
   }

   public function down(): void
   {
       Schema::table('users', function (Blueprint $table) {
           $table->dropUnique(['company_id', 'email']);
           $table->unique('email');
           $table->dropConstrainedForeignId('company_id');
       });
   }
   ```
   `company_id` is nullable **only** during Epic 1 so the existing stock users migration doesn't break; Task 11's signup flow always sets it, and Epic 2's first migration flips it to `nullable(false)`. Add a TODO comment in the migration referencing this.

4. **Add the relation on `User`.** In `app/Models/User.php`:
   ```php
   public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
   {
       return $this->belongsTo(\App\Modules\Tenancy\Models\Company::class);
   }
   ```
   Also add `'company_id'` to `$fillable`.

5. **Migrate and run.**
   ```
   php artisan migrate
   php artisan test --filter=UserBelongsToCompanyTest
   ```
   Expect: PASS.

6. **Commit.**
   ```
   git add -A
   git commit -m "feat(tenancy): link users to companies via company_id"
   ```

---

### Task 6 — `BelongsToCompany` trait + global scope + creating hook

**Files touched:** `app/Modules/Tenancy/Traits/BelongsToCompany.php`, `app/Modules/Tenancy/Scopes/CompanyScope.php`, `tests/Feature/Tenancy/BelongsToCompanyTraitTest.php`. For the test we create a throwaway migration and model because the trait applies to domain tables that don't exist yet.

**Steps:**

1. **Create the test fixture migration and model.** We'll use a table named `tenant_probes` that lives only in the test environment. Add `database/migrations/tests/xxxx_create_tenant_probes_table.php`:
   ```php
   <?php
   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration {
       public function up(): void
       {
           Schema::create('tenant_probes', function (Blueprint $t) {
               $t->id();
               $t->foreignId('company_id')->constrained()->cascadeOnDelete();
               $t->string('label');
               $t->timestamps();
           });
       }
       public function down(): void { Schema::dropIfExists('tenant_probes'); }
   };
   ```
   Register an additional migration path in `tests/TestCase.php` `setUp`:
   ```php
   protected function setUp(): void
   {
       parent::setUp();
       $this->loadMigrationsFrom(database_path('migrations/tests'));
   }
   ```

2. **Create the probe model** at `tests/Fixtures/TenantProbe.php`:
   ```php
   <?php
   namespace Tests\Fixtures;

   use App\Modules\Tenancy\Traits\BelongsToCompany;
   use Illuminate\Database\Eloquent\Model;

   class TenantProbe extends Model
   {
       use BelongsToCompany;
       protected $fillable = ['label'];
   }
   ```
   Add `"Tests\\": "tests/"` to `composer.json` `autoload-dev.psr-4` if not already; run `composer dump-autoload`.

3. **Write the failing tests** at `tests/Feature/Tenancy/BelongsToCompanyTraitTest.php`:
   ```php
   <?php

   namespace Tests\Feature\Tenancy;

   use App\Models\User;
   use App\Modules\Tenancy\Models\Company;
   use Illuminate\Foundation\Testing\RefreshDatabase;
   use Tests\Fixtures\TenantProbe;
   use Tests\TestCase;

   class BelongsToCompanyTraitTest extends TestCase
   {
       use RefreshDatabase;

       public function test_global_scope_hides_other_companies_rows(): void
       {
           [$coA, $coB] = $this->makeTwoCompanies();
           $userA = User::factory()->create(['company_id' => $coA->id]);

           TenantProbe::withoutGlobalScopes()->create(['company_id' => $coA->id, 'label' => 'A1']);
           TenantProbe::withoutGlobalScopes()->create(['company_id' => $coB->id, 'label' => 'B1']);

           $this->actingAs($userA);
           $visible = TenantProbe::pluck('label')->all();
           $this->assertSame(['A1'], $visible);
       }

       public function test_creating_hook_auto_fills_company_id(): void
       {
           [$coA] = $this->makeTwoCompanies();
           $userA = User::factory()->create(['company_id' => $coA->id]);
           $this->actingAs($userA);

           $probe = TenantProbe::create(['label' => 'auto']);
           $this->assertSame($coA->id, $probe->company_id);
       }

       private function makeTwoCompanies(): array
       {
           return [
               Company::create(['name' => 'A', 'cnpj' => '11111111000111', 'timezone' => 'UTC', 'status' => 'active']),
               Company::create(['name' => 'B', 'cnpj' => '22222222000122', 'timezone' => 'UTC', 'status' => 'active']),
           ];
       }
   }
   ```

4. **Run, expect FAIL.**
   ```
   php artisan test --filter=BelongsToCompanyTraitTest
   ```
   Expect: `Trait "App\Modules\Tenancy\Traits\BelongsToCompany" not found`.

5. **Create the scope** at `app/Modules/Tenancy/Scopes/CompanyScope.php`:
   ```php
   <?php

   namespace App\Modules\Tenancy\Scopes;

   use Illuminate\Database\Eloquent\Builder;
   use Illuminate\Database\Eloquent\Model;
   use Illuminate\Database\Eloquent\Scope;

   class CompanyScope implements Scope
   {
       public function apply(Builder $builder, Model $model): void
       {
           if ($companyId = auth()->user()?->company_id) {
               $builder->where($model->qualifyColumn('company_id'), $companyId);
           }
       }
   }
   ```

6. **Create the trait** at `app/Modules/Tenancy/Traits/BelongsToCompany.php`:
   ```php
   <?php

   namespace App\Modules\Tenancy\Traits;

   use App\Modules\Tenancy\Models\Company;
   use App\Modules\Tenancy\Scopes\CompanyScope;
   use Illuminate\Database\Eloquent\Relations\BelongsTo;

   trait BelongsToCompany
   {
       public static function bootBelongsToCompany(): void
       {
           static::addGlobalScope(new CompanyScope());

           static::creating(function ($model) {
               if (empty($model->company_id) && ($id = auth()->user()?->company_id)) {
                   $model->company_id = $id;
               }
           });
       }

       public function company(): BelongsTo
       {
           return $this->belongsTo(Company::class);
       }
   }
   ```

7. **Run, expect PASS.**
   ```
   php artisan test --filter=BelongsToCompanyTraitTest
   ```
   Expect: 2 passing tests.

8. **Commit.**
   ```
   git add -A
   git commit -m "feat(tenancy): add BelongsToCompany trait with global scope and creating hook"
   ```

---

### Task 7 — Migrator role, RLS enablement, and `EnsureTenantContext` middleware

**Files touched:** new migration `xxxx_enable_rls_on_tenant_tables.php`, `app/Modules/Tenancy/Http/Middleware/EnsureTenantContext.php`, `bootstrap/app.php`, `tests/Feature/Tenancy/RlsPolicyTest.php`, README update.

**Steps:**

1. **Manual DB role setup (flag for user confirmation).** The application runs as a non-superuser role subject to RLS. Migrations run as the same role, but policies must not interfere with DDL. We `FORCE ROW LEVEL SECURITY` so even the table owner is subject to the policy on DML, then rely on `SET LOCAL app.current_company_id` being set before any query touches these tables. Document this at `docs/setup/postgres.md`:
   ```
   # Postgres setup
   CREATE USER fleetis WITH PASSWORD 'fleetis' CREATEDB;
   CREATE DATABASE fleetis_v2 OWNER fleetis;
   -- Migrations and runtime use the same role. RLS is enforced on both via FORCE.
   ```

2. **Write the failing test** at `tests/Feature/Tenancy/RlsPolicyTest.php`:
   ```php
   <?php

   namespace Tests\Feature\Tenancy;

   use App\Modules\Tenancy\Models\Company;
   use Illuminate\Foundation\Testing\RefreshDatabase;
   use Illuminate\Support\Facades\DB;
   use Tests\Fixtures\TenantProbe;
   use Tests\TestCase;

   class RlsPolicyTest extends TestCase
   {
       use RefreshDatabase;

       public function test_rls_blocks_raw_sql_cross_tenant_read(): void
       {
           $coA = Company::create(['name' => 'A', 'cnpj' => '11111111000111', 'timezone' => 'UTC', 'status' => 'active']);
           $coB = Company::create(['name' => 'B', 'cnpj' => '22222222000122', 'timezone' => 'UTC', 'status' => 'active']);

           DB::statement("SET LOCAL app.current_company_id = ?", [$coA->id]);
           TenantProbe::withoutGlobalScopes()->create(['company_id' => $coA->id, 'label' => 'A-row']);

           DB::statement("SET LOCAL app.current_company_id = ?", [$coB->id]);
           TenantProbe::withoutGlobalScopes()->create(['company_id' => $coB->id, 'label' => 'B-row']);

           // Now acting as A: raw SQL that bypasses Eloquent scope MUST still see only A rows.
           DB::statement("SET LOCAL app.current_company_id = ?", [$coA->id]);
           $rows = DB::select('select label from tenant_probes order by label');
           $this->assertSame(['A-row'], array_column($rows, 'label'));
       }
   }
   ```
   Note: this test runs inside a transaction (`RefreshDatabase`), so `SET LOCAL` behaves correctly.

3. **Run, expect FAIL.**
   ```
   php artisan test --filter=RlsPolicyTest
   ```
   Expect: the assertion fails — both rows visible — because RLS isn't enabled yet.

4. **Create the RLS migration.** `php artisan make:migration enable_rls_on_tenant_tables`:
   ```php
   <?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Support\Facades\DB;

   return new class extends Migration {
       private array $tables = ['users', 'tenant_probes'];

       public function up(): void
       {
           foreach ($this->tables as $table) {
               if (! $this->tableExists($table)) {
                   continue;
               }
               DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
               DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
               DB::statement("
                   CREATE POLICY {$table}_tenant_isolation ON {$table}
                   USING (company_id = current_setting('app.current_company_id', true)::bigint)
                   WITH CHECK (company_id = current_setting('app.current_company_id', true)::bigint)
               ");
           }
       }

       public function down(): void
       {
           foreach ($this->tables as $table) {
               if (! $this->tableExists($table)) {
                   continue;
               }
               DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
               DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
           }
       }

       private function tableExists(string $t): bool
       {
           return (bool) DB::selectOne('select to_regclass(?) as r', [$t])->r;
       }
   };
   ```
   `tenant_probes` only exists in the test migration path; the `tableExists` guard skips it cleanly in production migrations. Subsequent epics amend the `$tables` array via new migrations.

5. **Migrate.**
   ```
   php artisan migrate
   ```
   Expect: `Migrated`. Re-running `php artisan migrate:fresh` for tests will apply it too because the test migration path loads after `up()` of base migrations — order matters. If the RLS migration runs before `tenant_probes` exists, the guard skips it. To re-apply RLS to tables created later (including the test probe), we need a second pass: add to `tests/TestCase.php` `setUp()` after `loadMigrationsFrom`:
   ```php
   \Illuminate\Support\Facades\Artisan::call('migrate', ['--path' => 'database/migrations/rls', '--force' => true]);
   ```
   And move the RLS migration into `database/migrations/rls/`. This pattern lets every epic re-run RLS application after their own tables land. Document this in `docs/setup/postgres.md`.

6. **Run, expect PASS.**
   ```
   php artisan test --filter=RlsPolicyTest
   ```
   Expect: 1 passing test. **This is the crucial RLS validation test called for in the brief.**

7. **Create the middleware** at `app/Modules/Tenancy/Http/Middleware/EnsureTenantContext.php`:
   ```php
   <?php

   namespace App\Modules\Tenancy\Http\Middleware;

   use Closure;
   use Illuminate\Http\Request;
   use Illuminate\Support\Facades\DB;
   use Symfony\Component\HttpFoundation\Response;

   class EnsureTenantContext
   {
       public function handle(Request $request, Closure $next): Response
       {
           $user = $request->user();
           abort_unless($user && $user->company_id, 403, 'Missing tenant context.');

           DB::statement('SET LOCAL app.current_company_id = ?', [$user->company_id]);

           return $next($request);
       }
   }
   ```
   `SET LOCAL` requires an active transaction. Wrap the middleware in one by registering it as a terminable-style middleware, or simpler: begin a transaction at the start of `handle` and commit on response. We keep it simple — prepend `DB::beginTransaction()` and register a terminating callback:
   ```php
   public function handle(Request $request, Closure $next): Response
   {
       $user = $request->user();
       abort_unless($user && $user->company_id, 403, 'Missing tenant context.');

       DB::beginTransaction();
       DB::statement('SET LOCAL app.current_company_id = ?', [$user->company_id]);

       try {
           $response = $next($request);
           DB::commit();
           return $response;
       } catch (\Throwable $e) {
           DB::rollBack();
           throw $e;
       }
   }
   ```
   Yes, this means every authenticated request runs in a DB transaction. For a small SaaS this is fine and actually improves consistency. Revisit if p99 latency becomes an issue.

8. **Register the middleware alias** in `bootstrap/app.php`:
   ```php
   ->withMiddleware(function (Middleware $middleware) {
       $middleware->alias([
           'tenant' => \App\Modules\Tenancy\Http\Middleware\EnsureTenantContext::class,
       ]);
       $middleware->web(append: []);
   })
   ```
   Group assignment to auth routes happens in Task 11.

9. **Commit.**
   ```
   git add -A
   git commit -m "feat(tenancy): enable PostgreSQL RLS and add EnsureTenantContext middleware"
   ```

---

### Task 8 — Install Spatie permission v6 with teams

**Files touched:** `config/permission.php`, migrations, `app/Models/User.php`, `tests/Feature/Identity/PermissionConfigTest.php`.

**Steps:**

1. **Write the failing test** at `tests/Feature/Identity/PermissionConfigTest.php`:
   ```php
   <?php

   namespace Tests\Feature\Identity;

   use Tests\TestCase;

   class PermissionConfigTest extends TestCase
   {
       public function test_teams_feature_is_enabled_and_keyed_on_company_id(): void
       {
           $this->assertTrue(config('permission.teams'));
           $this->assertSame('company_id', config('permission.column_names.team_foreign_key'));
       }
   }
   ```

2. **Run, expect FAIL.**
   ```
   php artisan test --filter=PermissionConfigTest
   ```
   Expect: config key missing → false/null assertions fail.

3. **Install the package.**
   ```
   composer require spatie/laravel-permission:^6
   php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
   ```

4. **Edit `config/permission.php`.**
   ```php
   'teams' => true,
   'column_names' => [
       'role_pivot_key' => null,
       'permission_pivot_key' => null,
       'model_morph_key' => 'model_id',
       'team_foreign_key' => 'company_id',
   ],
   ```

5. **Migrate.**
   ```
   php artisan migrate
   ```
   Expect: Spatie tables created with `company_id` as the team foreign key.

6. **Add `HasRoles` trait to User.** In `app/Models/User.php`:
   ```php
   use \Spatie\Permission\Traits\HasRoles;
   ```

7. **Run, expect PASS.**
   ```
   php artisan test --filter=PermissionConfigTest
   ```
   Expect: 1 passing test.

8. **Commit.**
   ```
   git add -A
   git commit -m "feat(identity): install spatie/laravel-permission with teams keyed on company_id"
   ```

---

### Task 9 — Install `spatie/laravel-data`

**Files touched:** `composer.json`, `config/data.php`.

**Steps:**

1. **Install.**
   ```
   composer require spatie/laravel-data:^4
   php artisan vendor:publish --provider="Spatie\LaravelData\LaravelDataServiceProvider" --tag=data-config
   ```

2. **Write a trivial smoke test** at `tests/Feature/DataPackageTest.php`:
   ```php
   <?php

   namespace Tests\Feature;

   use Spatie\LaravelData\Data;
   use Tests\TestCase;

   class DataPackageTest extends TestCase
   {
       public function test_data_class_serializes(): void
       {
           $dto = new class('Fleetis') extends Data {
               public function __construct(public string $name) {}
           };
           $this->assertSame(['name' => 'Fleetis'], $dto->toArray());
       }
   }
   ```

3. **Run, expect PASS.**
   ```
   php artisan test --filter=DataPackageTest
   ```

4. **Commit.**
   ```
   git add -A
   git commit -m "chore: install spatie/laravel-data"
   ```

---

### Task 10 — Role seeder (Admin, Operator, Financial)

**Files touched:** `app/Modules/Identity/Actions/SeedCompanyRolesAction.php`, `tests/Feature/Identity/SeedCompanyRolesActionTest.php`.

We seed roles **per company** inside the signup transaction, not globally. This action is the reusable unit.

**Steps:**

1. **Write the failing test** at `tests/Feature/Identity/SeedCompanyRolesActionTest.php`:
   ```php
   <?php

   namespace Tests\Feature\Identity;

   use App\Modules\Identity\Actions\SeedCompanyRolesAction;
   use App\Modules\Tenancy\Models\Company;
   use Illuminate\Foundation\Testing\RefreshDatabase;
   use Spatie\Permission\Models\Role;
   use Tests\TestCase;

   class SeedCompanyRolesActionTest extends TestCase
   {
       use RefreshDatabase;

       public function test_it_creates_three_roles_for_a_company(): void
       {
           $company = Company::create(['name' => 'A', 'cnpj' => '11111111000111', 'timezone' => 'UTC', 'status' => 'active']);

           app(SeedCompanyRolesAction::class)->handle($company);

           $roles = Role::where('company_id', $company->id)->pluck('name')->all();
           sort($roles);
           $this->assertSame(['Admin', 'Financial', 'Operator'], $roles);
       }

       public function test_roles_are_scoped_to_their_company(): void
       {
           $a = Company::create(['name' => 'A', 'cnpj' => '11111111000111', 'timezone' => 'UTC', 'status' => 'active']);
           $b = Company::create(['name' => 'B', 'cnpj' => '22222222000122', 'timezone' => 'UTC', 'status' => 'active']);

           app(SeedCompanyRolesAction::class)->handle($a);
           app(SeedCompanyRolesAction::class)->handle($b);

           $this->assertSame(3, Role::where('company_id', $a->id)->count());
           $this->assertSame(3, Role::where('company_id', $b->id)->count());
       }
   }
   ```

2. **Run, expect FAIL.**
   ```
   php artisan test --filter=SeedCompanyRolesActionTest
   ```
   Expect: class not found.

3. **Create the action** at `app/Modules/Identity/Actions/SeedCompanyRolesAction.php`:
   ```php
   <?php

   namespace App\Modules\Identity\Actions;

   use App\Modules\Tenancy\Models\Company;
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
       }
   }
   ```

4. **Run, expect PASS.**
   ```
   php artisan test --filter=SeedCompanyRolesActionTest
   ```

5. **Commit.**
   ```
   git add -A
   git commit -m "feat(identity): SeedCompanyRolesAction creates Admin/Operator/Financial per company"
   ```

---

### Task 11 — Signup flow (company + admin user atomically)

**Files touched:** `app/Modules/Identity/Http/Controllers/CompanyRegisterController.php`, `app/Modules/Identity/Http/Requests/CompanyRegisterRequest.php`, `app/Modules/Identity/Actions/RegisterCompanyAction.php`, `resources/js/Pages/Auth/RegisterCompany.vue`, `routes/auth.php`, `tests/Feature/Auth/CompanyRegistrationTest.php`.

**Steps:**

1. **Write the failing test** at `tests/Feature/Auth/CompanyRegistrationTest.php`:
   ```php
   <?php

   namespace Tests\Feature\Auth;

   use App\Models\User;
   use App\Modules\Tenancy\Models\Company;
   use Illuminate\Foundation\Testing\RefreshDatabase;
   use Spatie\Permission\Models\Role;
   use Tests\TestCase;

   class CompanyRegistrationTest extends TestCase
   {
       use RefreshDatabase;

       public function test_signup_creates_company_user_and_roles_atomically(): void
       {
           $response = $this->post('/register', [
               'company_name' => 'Transportadora X',
               'cnpj' => '33333333000133',
               'name' => 'Alice Admin',
               'email' => 'alice@x.test',
               'password' => 'password-123',
               'password_confirmation' => 'password-123',
           ]);

           $response->assertRedirect('/dashboard');

           $company = Company::where('cnpj', '33333333000133')->first();
           $this->assertNotNull($company);

           $user = User::where('email', 'alice@x.test')->first();
           $this->assertSame($company->id, $user->company_id);

           $this->assertSame(3, Role::where('company_id', $company->id)->count());
           app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($company->id);
           $this->assertTrue($user->hasRole('Admin'));
       }

       public function test_signup_rolls_back_on_failure(): void
       {
           Company::create(['name' => 'Pre', 'cnpj' => '44444444000144', 'timezone' => 'UTC', 'status' => 'active']);

           $response = $this->post('/register', [
               'company_name' => 'Transportadora Y',
               'cnpj' => '44444444000144', // duplicate — will fail
               'name' => 'Bob',
               'email' => 'bob@y.test',
               'password' => 'password-123',
               'password_confirmation' => 'password-123',
           ]);

           $response->assertSessionHasErrors('cnpj');
           $this->assertNull(User::where('email', 'bob@y.test')->first());
       }
   }
   ```

2. **Run, expect FAIL.**
   ```
   php artisan test --filter=CompanyRegistrationTest
   ```
   Expect: default Breeze register endpoint doesn't understand `company_name` / `cnpj`, no company created.

3. **Create the request** `app/Modules/Identity/Http/Requests/CompanyRegisterRequest.php`:
   ```php
   <?php

   namespace App\Modules\Identity\Http\Requests;

   use Illuminate\Foundation\Http\FormRequest;
   use Illuminate\Validation\Rules\Password;

   class CompanyRegisterRequest extends FormRequest
   {
       public function authorize(): bool { return true; }

       public function rules(): array
       {
           return [
               'company_name' => ['required', 'string', 'max:255'],
               'cnpj' => ['required', 'string', 'size:14', 'unique:companies,cnpj'],
               'name' => ['required', 'string', 'max:255'],
               'email' => ['required', 'email', 'max:255'],
               'password' => ['required', 'confirmed', Password::defaults()],
           ];
       }
   }
   ```

4. **Create the action** `app/Modules/Identity/Actions/RegisterCompanyAction.php`:
   ```php
   <?php

   namespace App\Modules\Identity\Actions;

   use App\Models\User;
   use App\Modules\Tenancy\Models\Company;
   use Illuminate\Support\Facades\DB;
   use Illuminate\Support\Facades\Hash;
   use Spatie\Permission\PermissionRegistrar;

   class RegisterCompanyAction
   {
       public function __construct(private SeedCompanyRolesAction $seedRoles) {}

       public function handle(array $input): User
       {
           return DB::transaction(function () use ($input) {
               $company = Company::create([
                   'name' => $input['company_name'],
                   'cnpj' => $input['cnpj'],
                   'timezone' => 'America/Sao_Paulo',
                   'status' => 'active',
               ]);

               $user = User::create([
                   'company_id' => $company->id,
                   'name' => $input['name'],
                   'email' => $input['email'],
                   'password' => Hash::make($input['password']),
               ]);

               $this->seedRoles->handle($company);

               app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
               $user->assignRole('Admin');

               return $user;
           });
       }
   }
   ```

5. **Create the controller** `app/Modules/Identity/Http/Controllers/CompanyRegisterController.php`:
   ```php
   <?php

   namespace App\Modules\Identity\Http\Controllers;

   use App\Http\Controllers\Controller;
   use App\Modules\Identity\Actions\RegisterCompanyAction;
   use App\Modules\Identity\Http\Requests\CompanyRegisterRequest;
   use Illuminate\Auth\Events\Registered;
   use Illuminate\Http\RedirectResponse;
   use Illuminate\Support\Facades\Auth;
   use Inertia\Inertia;
   use Inertia\Response;

   class CompanyRegisterController extends Controller
   {
       public function create(): Response
       {
           return Inertia::render('Auth/RegisterCompany');
       }

       public function store(CompanyRegisterRequest $request, RegisterCompanyAction $action): RedirectResponse
       {
           $user = $action->handle($request->validated());

           event(new Registered($user));
           Auth::login($user);

           return redirect()->intended('/dashboard');
       }
   }
   ```

6. **Rewire the route in `routes/auth.php`.** Replace the Breeze register routes:
   ```php
   Route::get('register', [\App\Modules\Identity\Http\Controllers\CompanyRegisterController::class, 'create'])
       ->middleware('guest')->name('register');
   Route::post('register', [\App\Modules\Identity\Http\Controllers\CompanyRegisterController::class, 'store'])
       ->middleware('guest');
   ```
   Remove Breeze's `RegisteredUserController` import if unused.

7. **Create the Vue page** `resources/js/Pages/Auth/RegisterCompany.vue` (Options API). Mirror Breeze's `Register.vue` but with `company_name` and `cnpj` fields added and `useForm` in `data()`.

8. **Run, expect PASS.**
   ```
   php artisan test --filter=CompanyRegistrationTest
   npm run build
   ```

9. **Commit.**
   ```
   git add -A
   git commit -m "feat(auth): company+admin atomic signup replaces Breeze register"
   ```

---

### Task 12 — Wire `EnsureTenantContext` to authenticated routes + base `TenantPolicy`

**Files touched:** `routes/web.php`, `app/Policies/TenantPolicy.php`, `app/Http/Kernel.php` (if present) or `bootstrap/app.php`, `tests/Feature/Tenancy/EnsureTenantContextMiddlewareTest.php`.

**Steps:**

1. **Write the failing test** at `tests/Feature/Tenancy/EnsureTenantContextMiddlewareTest.php`:
   ```php
   <?php

   namespace Tests\Feature\Tenancy;

   use App\Models\User;
   use App\Modules\Tenancy\Models\Company;
   use Illuminate\Foundation\Testing\RefreshDatabase;
   use Tests\TestCase;

   class EnsureTenantContextMiddlewareTest extends TestCase
   {
       use RefreshDatabase;

       public function test_authenticated_user_without_company_is_403(): void
       {
           $user = User::factory()->create(['company_id' => null]);
           $this->actingAs($user);
           $this->get('/dashboard')->assertStatus(403);
       }

       public function test_authenticated_user_with_company_reaches_dashboard(): void
       {
           $company = Company::create(['name' => 'A', 'cnpj' => '55555555000155', 'timezone' => 'UTC', 'status' => 'active']);
           $user = User::factory()->create(['company_id' => $company->id]);
           $this->actingAs($user);
           $this->get('/dashboard')->assertStatus(200);
       }
   }
   ```

2. **Run, expect FAIL.**
   ```
   php artisan test --filter=EnsureTenantContextMiddlewareTest
   ```
   Expect: first test passes if user isn't actually blocked (might return 200 or 500) — actual failure depends on current routing. Confirm both tests do not both pass.

3. **Attach `tenant` middleware to the auth group** in `routes/web.php`:
   ```php
   Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
       Route::get('/dashboard', fn () => \Inertia\Inertia::render('Dashboard'))->name('dashboard');
   });
   ```

4. **Create `app/Policies/TenantPolicy.php`** as a base class for future policies:
   ```php
   <?php

   namespace App\Policies;

   use App\Models\User;
   use Illuminate\Database\Eloquent\Model;

   abstract class TenantPolicy
   {
       protected function sameCompany(User $user, Model $model): bool
       {
           return isset($model->company_id) && $model->company_id === $user->company_id;
       }
   }
   ```
   Minimal on purpose. Epics 2+ extend it.

5. **Run, expect PASS.**
   ```
   php artisan test --filter=EnsureTenantContextMiddlewareTest
   ```

6. **Commit.**
   ```
   git add -A
   git commit -m "feat(tenancy): apply EnsureTenantContext to auth routes and add TenantPolicy base"
   ```

---

### Task 13 — `TenantTestCase` harness and cross-tenant leak test

**Files touched:** `tests/TenantTestCase.php`, `tests/Feature/Tenancy/CrossTenantLeakTest.php`.

**Steps:**

1. **Create the harness** at `tests/TenantTestCase.php`:
   ```php
   <?php

   namespace Tests;

   use App\Models\User;
   use App\Modules\Identity\Actions\SeedCompanyRolesAction;
   use App\Modules\Tenancy\Models\Company;
   use Illuminate\Foundation\Testing\RefreshDatabase;
   use Spatie\Permission\PermissionRegistrar;

   abstract class TenantTestCase extends TestCase
   {
       use RefreshDatabase;

       protected Company $companyA;
       protected Company $companyB;
       protected User $adminA;
       protected User $adminB;

       protected function setUp(): void
       {
           parent::setUp();

           $this->companyA = Company::create(['name' => 'A', 'cnpj' => '11111111000111', 'timezone' => 'UTC', 'status' => 'active']);
           $this->companyB = Company::create(['name' => 'B', 'cnpj' => '22222222000122', 'timezone' => 'UTC', 'status' => 'active']);

           app(SeedCompanyRolesAction::class)->handle($this->companyA);
           app(SeedCompanyRolesAction::class)->handle($this->companyB);

           $this->adminA = User::factory()->create(['company_id' => $this->companyA->id]);
           $this->adminB = User::factory()->create(['company_id' => $this->companyB->id]);

           app(PermissionRegistrar::class)->setPermissionsTeamId($this->companyA->id);
           $this->adminA->assignRole('Admin');
           app(PermissionRegistrar::class)->setPermissionsTeamId($this->companyB->id);
           $this->adminB->assignRole('Admin');
       }

       protected function asCompanyA(): self
       {
           $this->actingAs($this->adminA);
           app(PermissionRegistrar::class)->setPermissionsTeamId($this->companyA->id);
           return $this;
       }

       protected function asCompanyB(): self
       {
           $this->actingAs($this->adminB);
           app(PermissionRegistrar::class)->setPermissionsTeamId($this->companyB->id);
           return $this;
       }
   }
   ```

2. **Write the cross-tenant leak test** at `tests/Feature/Tenancy/CrossTenantLeakTest.php`. Since Epic 1 has no tenant-scoped resource endpoint yet, we prove the harness against `users` and the `tenant_probes` fixture:
   ```php
   <?php

   namespace Tests\Feature\Tenancy;

   use Illuminate\Support\Facades\DB;
   use Tests\Fixtures\TenantProbe;
   use Tests\TenantTestCase;

   class CrossTenantLeakTest extends TenantTestCase
   {
       public function test_eloquent_scope_isolates_tenants_on_users_table(): void
       {
           $this->asCompanyA();
           $visible = \App\Models\User::pluck('id')->all();
           $this->assertContains($this->adminA->id, $visible);
           $this->assertNotContains($this->adminB->id, $visible);
       }

       public function test_rls_isolates_tenants_on_raw_sql(): void
       {
           TenantProbe::withoutGlobalScopes()->create(['company_id' => $this->companyA->id, 'label' => 'A-row']);
           TenantProbe::withoutGlobalScopes()->create(['company_id' => $this->companyB->id, 'label' => 'B-row']);

           DB::statement('SET LOCAL app.current_company_id = ?', [$this->companyA->id]);
           $rows = DB::select('select label from tenant_probes');
           $this->assertSame(['A-row'], array_column($rows, 'label'));
       }
   }
   ```
   The first test requires `User` to use `BelongsToCompany`. Add `use BelongsToCompany;` to `app/Models/User.php` — but **do not** let the trait's `creating` hook override `company_id` when it's explicitly set. The trait already guards with `empty($model->company_id)`, so we're safe.

3. **Run, expect FAIL on first test** until the trait is applied to `User`, then PASS.
   ```
   php artisan test --filter=CrossTenantLeakTest
   ```
   Expect: first run fails on test 1; add trait to User; second run: 2 passing tests.

4. **Commit.**
   ```
   git add -A
   git commit -m "test(tenancy): add TenantTestCase harness and cross-tenant leak tests"
   ```

---

### Task 14 — Tooling: Pint, Larastan, ESLint

**Files touched:** `pint.json`, `phpstan.neon`, `eslint.config.js`, `package.json` scripts, `tests/Feature/RlsCoverageTest.php`.

**Steps:**

1. **Pint config** at `pint.json`:
   ```json
   { "preset": "laravel" }
   ```
   Run `vendor/bin/pint --test` and expect any formatting issues; run `vendor/bin/pint` to fix. Commit changes as their own commit at the end if Pint touched anything.

2. **Install Larastan.**
   ```
   composer require --dev larastan/larastan:^3
   ```
   Create `phpstan.neon`:
   ```
   includes:
       - vendor/larastan/larastan/extension.neon
   parameters:
       level: 6
       paths:
           - app
           - tests
       excludePaths:
           - tests/Fixtures
   ```
   Run `vendor/bin/phpstan analyse` and fix any issues (expect a few docblock additions on the trait and on the `User::$company` accessor).

3. **Install ESLint 9 + Vue plugin.**
   ```
   npm i -D eslint@^9 eslint-plugin-vue@^9 globals
   ```
   Create `eslint.config.js` at the project root:
   ```js
   import vue from 'eslint-plugin-vue'
   import globals from 'globals'

   export default [
     ...vue.configs['flat/recommended'],
     {
       files: ['resources/js/**/*.{js,vue}'],
       languageOptions: {
         ecmaVersion: 'latest',
         sourceType: 'module',
         globals: { ...globals.browser, route: 'readonly' },
       },
       rules: {
         'vue/multi-word-component-names': 'off',
         'vue/component-api-style': ['error', ['options']],
       },
     },
   ]
   ```
   The `component-api-style: options` rule enforces Options API across the codebase — this is the convention from the MVP doc.
   Add to `package.json`:
   ```json
   "lint": "eslint resources/js --ext .js,.vue",
   "lint:fix": "eslint resources/js --ext .js,.vue --fix"
   ```
   Run `npm run lint` and fix reports (expect some from Breeze-generated Composition API files → convert them to Options API or add per-file overrides for the ones you haven't migrated yet; long-term target is zero overrides).

4. **Add the RLS coverage test** at `tests/Feature/RlsCoverageTest.php` — this is the guardrail that future epics can't forget to enable RLS:
   ```php
   <?php

   namespace Tests\Feature;

   use Illuminate\Foundation\Testing\RefreshDatabase;
   use Illuminate\Support\Facades\DB;
   use Tests\TestCase;

   class RlsCoverageTest extends TestCase
   {
       use RefreshDatabase;

       /**
        * Every table with a company_id column must have RLS enabled and a tenant policy.
        */
       public function test_every_tenant_scoped_table_has_rls(): void
       {
           $tenantTables = DB::select("
               select c.table_name
               from information_schema.columns c
               where c.table_schema = 'public'
                 and c.column_name = 'company_id'
           ");

           $missing = [];
           foreach ($tenantTables as $row) {
               $rls = DB::selectOne("
                   select relrowsecurity as enabled, relforcerowsecurity as forced
                   from pg_class where relname = ?", [$row->table_name]);
               $policy = DB::selectOne("
                   select 1 as found from pg_policies
                   where schemaname='public' and tablename=? limit 1", [$row->table_name]);
               if (! $rls?->enabled || ! $rls?->forced || ! $policy) {
                   $missing[] = $row->table_name;
               }
           }

           $this->assertEmpty(
               $missing,
               'Tables with company_id missing RLS/policy: '.implode(', ', $missing)
           );
       }
   }
   ```
   The Spatie permission tables (`model_has_roles`, `model_has_permissions`, `role_has_permissions`) use `team_id` (aliased to `company_id` via config) — verify this test handles them. If `column_names.team_foreign_key` is `company_id`, the Spatie migrations create a column literally named `company_id`, so they'll show up in this query. They need RLS too. Add them to Task 7's RLS migration list before this test passes, or extend the RLS migration now:
   ```php
   private array $tables = ['users', 'tenant_probes', 'roles', 'model_has_roles', 'model_has_permissions', 'role_has_permissions'];
   ```
   Actually — `roles` and `role_has_permissions` rows are per-company via `company_id` column; `model_has_roles`/`model_has_permissions` too. Enable RLS on all four.

5. **Run full test suite.**
   ```
   php artisan test
   vendor/bin/pint --test
   vendor/bin/phpstan analyse
   npm run lint
   npm run build
   ```
   Expect: all green.

6. **Commit.**
   ```
   git add -A
   git commit -m "chore: add Pint, Larastan level 6, ESLint Options-API enforcement, and RLS coverage test"
   ```

---

### Task 15 — CI pipeline (GitHub Actions)

**Files touched:** `.github/workflows/ci.yml`.

**Steps:**

1. **Write the workflow** at `.github/workflows/ci.yml`:
   ```yaml
   name: CI
   on:
     push:
       branches: [main]
     pull_request:

   jobs:
     test:
       runs-on: ubuntu-24.04
       services:
         postgres:
           image: postgres:16
           env:
             POSTGRES_USER: fleetis
             POSTGRES_PASSWORD: fleetis
             POSTGRES_DB: fleetis_v2
           ports: ['5432:5432']
           options: >-
             --health-cmd "pg_isready -U fleetis"
             --health-interval 10s
             --health-timeout 5s
             --health-retries 5
       env:
         DB_CONNECTION: pgsql
         DB_HOST: 127.0.0.1
         DB_PORT: 5432
         DB_DATABASE: fleetis_v2
         DB_USERNAME: fleetis
         DB_PASSWORD: fleetis
       steps:
         - uses: actions/checkout@v4
         - uses: shivammathur/setup-php@v2
           with:
             php-version: '8.3'
             extensions: mbstring, pdo_pgsql, intl, bcmath
             coverage: none
         - uses: actions/setup-node@v4
           with:
             node-version: '20'
             cache: 'npm'
         - name: Composer cache
           uses: actions/cache@v4
           with:
             path: vendor
             key: composer-${{ hashFiles('composer.lock') }}
         - run: composer install --no-interaction --prefer-dist
         - run: cp .env.example .env
         - run: php artisan key:generate
         - run: npm ci
         - run: npm run build
         - run: php artisan migrate --force
         - run: php artisan test
         - run: vendor/bin/pint --test
         - run: vendor/bin/phpstan analyse --no-progress
         - run: npm run lint
   ```

2. **Sanity test the workflow locally.** There's no first-class local GitHub Actions runner we rely on; instead, run each step manually to confirm it works:
   ```
   composer install --no-interaction --prefer-dist
   cp .env.example .env && php artisan key:generate
   npm ci && npm run build
   php artisan migrate:fresh --force
   php artisan test
   vendor/bin/pint --test
   vendor/bin/phpstan analyse --no-progress
   npm run lint
   ```
   Expect: all green. If pint or phpstan finds issues at this stage, fix them before pushing.

3. **Commit.**
   ```
   git add -A
   git commit -m "ci: add GitHub Actions workflow for tests, pint, phpstan, eslint"
   ```

---

## Summary of Task Count and Acceptance

15 tasks, each roughly 2–5 minutes per step, 4–9 steps per task. Total estimated effort: 1.5–2 days focused work.

**Acceptance criteria for Epic 1 complete:**
1. `php artisan test` — all tests pass, including the crucial RLS cross-tenant leak test and the RLS coverage guardrail.
2. A user can visit `/register`, submit company + admin details, and land on `/dashboard`.
3. `vendor/bin/pint --test`, `vendor/bin/phpstan analyse` (level 6), and `npm run lint` all clean.
4. CI is green on a dummy PR.
5. Two-company seed data behaves correctly: each company's admin sees only their own data in both Eloquent queries and raw SQL.

## Risks and Watch Items for the Implementing Dev

1. **Spatie `teams` + `PermissionRegistrar::setPermissionsTeamId`.** You must call `setPermissionsTeamId($companyId)` before any role check in tests and in the signup action. Forgetting this is the #1 source of confusing test failures with the teams feature.
2. **`SET LOCAL` scope.** Only lives inside a transaction. The middleware wraps each request in one; don't remove that. If a future epic adds long-running CLI jobs, they'll need their own tenant-context bootstrapping.
3. **Migration ordering vs. RLS.** New tables added in Epic 2+ must be appended to the `$tables` array of a *new* RLS migration (don't edit the Epic 1 one — migrations are immutable once merged). Write a Pint-style custom test that fails the build if a new `company_id` column lacks RLS — we shipped that test in Task 14.
4. **`composer create-project` merge.** The file copy step in Task 1 is error-prone. Verify `.git/` and `docs/` still exist after, and that `git status` shows the new Laravel files as untracked before the first commit.
5. **Breeze register route is replaced, not extended.** If a later epic reinstalls Breeze or re-runs the Breeze scaffold, the replaced route will be clobbered. Add a note to `docs/setup/postgres.md` (or a new `docs/dev/gotchas.md`).
6. **RLS on Spatie tables.** Task 14 catches this, but flagging explicitly: `roles`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` all carry `company_id` and must have RLS enabled. Without this, a bug could let a user fetch another tenant's role assignments.

## Suggested Next Steps Before Kicking Off

- Confirm the manual Postgres user/db creation command is acceptable for the user's environment (or swap for a `docker-compose.yml` providing Postgres 16).
- Confirm the "every request in a DB transaction" trade-off in Task 7 step 7 is acceptable. Alternative is a custom `DB::listen` that re-sets the variable on connection reuse, but that's fragile.
- Decide whether to enforce Options API across the whole `resources/js` tree now (convert all Breeze pages in Task 3) or let it accrete over Epics 2–4. The plan leans "gradual" because it's cheaper, but the ESLint rule will flag anything non-compliant.

Relevant files (all currently absent — to be created by this plan):

- `/var/www/html/projects/fleetis-v2/docs/plans/fleetis-v2-mvp.md` (existing, reference only)
- `/var/www/html/projects/fleetis-v2/app/Modules/Tenancy/Models/Company.php`
- `/var/www/html/projects/fleetis-v2/app/Modules/Tenancy/Traits/BelongsToCompany.php`
- `/var/www/html/projects/fleetis-v2/app/Modules/Tenancy/Scopes/CompanyScope.php`
- `/var/www/html/projects/fleetis-v2/app/Modules/Tenancy/Http/Middleware/EnsureTenantContext.php`
- `/var/www/html/projects/fleetis-v2/app/Modules/Identity/Actions/SeedCompanyRolesAction.php`
- `/var/www/html/projects/fleetis-v2/app/Modules/Identity/Actions/RegisterCompanyAction.php`
- `/var/www/html/projects/fleetis-v2/app/Modules/Identity/Http/Controllers/CompanyRegisterController.php`
- `/var/www/html/projects/fleetis-v2/app/Modules/Identity/Http/Requests/CompanyRegisterRequest.php`
- `/var/www/html/projects/fleetis-v2/app/Policies/TenantPolicy.php`
- `/var/www/html/projects/fleetis-v2/database/migrations/rls/xxxx_enable_rls_on_tenant_tables.php`
- `/var/www/html/projects/fleetis-v2/tests/TenantTestCase.php`
- `/var/www/html/projects/fleetis-v2/tests/Feature/Tenancy/RlsPolicyTest.php`
- `/var/www/html/projects/fleetis-v2/tests/Feature/Tenancy/CrossTenantLeakTest.php`
- `/var/www/html/projects/fleetis-v2/tests/Feature/RlsCoverageTest.php`
- `/var/www/html/projects/fleetis-v2/.github/workflows/ci.yml`
