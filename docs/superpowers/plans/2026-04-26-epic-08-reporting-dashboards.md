# Epic 08 — Reporting: Dashboards & Summaries Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Reporting module — a financial dashboard with ApexCharts charts, per-vehicle fleet utilization report, and per-freight financial summary — completing the FleetisV2 MVP.

**Architecture:** New read-only `app/Modules/Reporting/` module with three service classes that encapsulate all aggregation queries. Controllers stay thin. The existing `Dashboard.vue` is upgraded to a full financial dashboard; new `Reporting/Vehicles.vue` and `Reporting/VehicleShow.vue` pages are added. Freight show page gets a financial summary section. Service class boundaries mean queries can be pointed at materialized views later without touching controllers.

**Tech Stack:** Laravel 11, Inertia.js, Vue 3 Options API, TailwindCSS, ApexCharts via `vue3-apexcharts`, PostgreSQL 16, `TenantTestCase` + `RefreshDatabase` for feature tests.

---

## File Map

### New — Backend
- `app/Modules/Reporting/Services/FinancialDashboardService.php` — revenue/expenses/AR/AP aggregations
- `app/Modules/Reporting/Services/VehicleReportService.php` — per-vehicle fleet metrics
- `app/Modules/Reporting/Services/FreightFinancialSummaryService.php` — receivable + expenses linked to a freight
- `app/Modules/Reporting/Http/Controllers/DashboardController.php` — thin dashboard controller
- `app/Modules/Reporting/Http/Controllers/VehicleReportController.php` — thin vehicle report controller

### Modified — Backend
- `app/Modules/Operations/Http/Controllers/FreightController.php` — inject FreightFinancialSummaryService into show()
- `routes/web.php` — replace dashboard closure with DashboardController; add /reports/vehicles routes

### New — Tests
- `tests/Feature/Reporting/DashboardControllerTest.php`
- `tests/Feature/Reporting/VehicleReportControllerTest.php`
- `tests/Feature/Reporting/FreightFinancialSummaryTest.php`

### New — Frontend
- `resources/js/Pages/Reporting/Vehicles.vue` — fleet utilization index
- `resources/js/Pages/Reporting/VehicleShow.vue` — per-vehicle drill-down

### Modified — Frontend
- `resources/js/Pages/Dashboard.vue` — KPI cards + area chart + status chart + recent freights
- `resources/js/Pages/Operations/Show.vue` — add Resumo Financeiro section
- `resources/js/Layouts/AuthenticatedLayout.vue` — add "Relatórios" nav group

---

## Task 1: Install ApexCharts

**Files:**
- Modify: `package.json` (via npm install)

- [ ] **Step 1: Install packages**

```bash
npm install apexcharts vue3-apexcharts
```

Expected output: `added N packages` with no errors.

- [ ] **Step 2: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore: install vue3-apexcharts for dashboard charts"
```

---

## Task 2: FinancialDashboardService + DashboardController + tests

**Files:**
- Create: `tests/Feature/Reporting/DashboardControllerTest.php`
- Create: `app/Modules/Reporting/Services/FinancialDashboardService.php`
- Create: `app/Modules/Reporting/Http/Controllers/DashboardController.php`
- Modify: `routes/web.php`

### Step 2.1 — Write failing tests

- [ ] **Step 1: Create test file**

Create `tests/Feature/Reporting/DashboardControllerTest.php`:

```php
<?php

namespace Tests\Feature\Reporting;

use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillInstallment;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class DashboardControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_all_roles_can_access_dashboard(): void
    {
        foreach (['Admin', 'Operator', 'Financial'] as $role) {
            $user = $this->makeUserWithRole($role);
            $this->actingAsTenant($user)->get('/dashboard')->assertOk();
        }
    }

    public function test_dashboard_returns_dashboard_component_with_required_props(): void
    {
        $user = $this->makeUserWithRole('Admin');

        $this->actingAsTenant($user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('revenueSeries')
                ->has('expenseSeries')
                ->has('arOutstanding')
                ->has('apOutstanding')
                ->has('freightByStatus')
                ->has('recentFreights')
                ->has('period')
            );
    }

    public function test_ar_outstanding_is_scoped_to_own_company(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        Receivable::factory()->create([
            'company_id'  => $userA->company_id,
            'amount_due'  => '1000.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);
        Receivable::factory()->create([
            'company_id'  => $userB->company_id,
            'amount_due'  => '9000.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);

        $this->actingAsTenant($userA)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('arOutstanding', fn ($v) => (float) $v === 1000.0)
            );
    }

    public function test_ar_outstanding_excludes_paid_receivables(): void
    {
        $user = $this->makeUserWithRole('Financial');

        Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'amount_due'  => '500.00',
            'amount_paid' => '500.00',
            'status'      => 'paid',
        ]);

        $this->actingAsTenant($user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('arOutstanding', fn ($v) => (float) $v === 0.0)
            );
    }

    public function test_ap_outstanding_is_scoped_to_own_company(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        $billA = Bill::factory()->create(['company_id' => $userA->company_id]);
        BillInstallment::factory()->create([
            'company_id'  => $userA->company_id,
            'bill_id'     => $billA->id,
            'amount'      => '500.00',
            'paid_amount' => null,
            'paid_at'     => null,
        ]);

        $billB = Bill::factory()->create(['company_id' => $userB->company_id]);
        BillInstallment::factory()->create([
            'company_id'  => $userB->company_id,
            'bill_id'     => $billB->id,
            'amount'      => '9000.00',
            'paid_amount' => null,
            'paid_at'     => null,
        ]);

        $this->actingAsTenant($userA)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('apOutstanding', fn ($v) => (float) $v === 500.0)
            );
    }

    public function test_ap_outstanding_excludes_paid_installments(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $bill = Bill::factory()->create(['company_id' => $user->company_id]);

        BillInstallment::factory()->create([
            'company_id'  => $user->company_id,
            'bill_id'     => $bill->id,
            'amount'      => '300.00',
            'paid_amount' => '300.00',
            'paid_at'     => now(),
        ]);

        $this->actingAsTenant($user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('apOutstanding', fn ($v) => (float) $v === 0.0)
            );
    }

    public function test_revenue_series_is_scoped_to_own_company(): void
    {
        $userA = $this->makeUserWithRole('Admin');
        $userB = $this->makeUserWithRole('Admin');

        Freight::factory()->create([
            'company_id'   => $userA->company_id,
            'freight_value' => '1000.00',
            'finished_at'  => now(),
        ]);
        Freight::factory()->create([
            'company_id'   => $userB->company_id,
            'freight_value' => '9000.00',
            'finished_at'  => now(),
        ]);

        $this->actingAsTenant($userA)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('revenueSeries', fn ($series) =>
                    collect($series)->sum('y') === 1000.0
                )
            );
    }

    public function test_period_query_param_is_returned_in_props(): void
    {
        $user = $this->makeUserWithRole('Admin');

        $this->actingAsTenant($user)->get('/dashboard?period=weekly')
            ->assertInertia(fn ($page) => $page->where('period', 'weekly'));
    }

    public function test_invalid_period_defaults_to_monthly(): void
    {
        $user = $this->makeUserWithRole('Admin');

        $this->actingAsTenant($user)->get('/dashboard?period=badvalue')
            ->assertInertia(fn ($page) => $page->where('period', 'monthly'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Reporting/DashboardControllerTest.php
```

Expected: `Class "App\Modules\Reporting\Http\Controllers\DashboardController" not found` or route not found errors.

### Step 2.2 — Implement FinancialDashboardService

- [ ] **Step 3: Create the service**

Create `app/Modules/Reporting/Services/FinancialDashboardService.php`:

```php
<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Finance\Models\BillInstallment;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\Models\Freight;
use Illuminate\Support\Facades\DB;

class FinancialDashboardService
{
    private const GRANULARITIES = ['day', 'week', 'month'];

    private function gran(string $granularity): string
    {
        return in_array($granularity, self::GRANULARITIES, true) ? $granularity : 'month';
    }

    public function revenueByPeriod(string $granularity): array
    {
        $gran = $this->gran($granularity);

        return Freight::query()
            ->selectRaw("date_trunc('$gran', finished_at) AS period, SUM(freight_value) AS total")
            ->whereNotNull('freight_value')
            ->whereNotNull('finished_at')
            ->groupByRaw("date_trunc('$gran', finished_at)")
            ->orderBy('period')
            ->get()
            ->map(fn ($r) => ['x' => substr($r->period, 0, 10), 'y' => (float) $r->total])
            ->toArray();
    }

    public function expensesByPeriod(string $granularity): array
    {
        $gran = $this->gran($granularity);
        $cid  = auth()->user()->company_id;

        $rows = DB::select("
            SELECT date_trunc('$gran', date) AS period, SUM(total) AS total
            FROM (
                SELECT incurred_on AS date, amount     AS total FROM expenses             WHERE company_id = ?
                UNION ALL
                SELECT fueled_at   AS date, total_cost AS total FROM fuel_records         WHERE company_id = ?
                UNION ALL
                SELECT performed_on AS date, cost      AS total FROM maintenance_records  WHERE company_id = ?
            ) combined
            GROUP BY 1
            ORDER BY 1
        ", [$cid, $cid, $cid]);

        return collect($rows)
            ->map(fn ($r) => ['x' => substr($r->period, 0, 10), 'y' => (float) $r->total])
            ->toArray();
    }

    public function arOutstanding(): string
    {
        return (string) Receivable::query()
            ->whereIn('status', ['open', 'partially_paid', 'overdue'])
            ->selectRaw('COALESCE(SUM(amount_due - amount_paid), 0) AS total')
            ->value('total');
    }

    public function apOutstanding(): string
    {
        return (string) BillInstallment::query()
            ->whereNull('paid_at')
            ->selectRaw('COALESCE(SUM(amount - COALESCE(paid_amount, 0)), 0) AS total')
            ->value('total');
    }

    public function freightByStatus(): array
    {
        return Freight::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status')
            ->toArray();
    }

    public function recentFreights(int $limit = 8): array
    {
        return Freight::query()
            ->with(['client:id,name', 'vehicle:id,license_plate,brand,model'])
            ->latest()
            ->limit($limit)
            ->get(['id', 'client_id', 'vehicle_id', 'status', 'freight_value', 'created_at'])
            ->toArray();
    }
}
```

### Step 2.3 — Implement DashboardController

- [ ] **Step 4: Create the controller**

Create `app/Modules/Reporting/Http/Controllers/DashboardController.php`:

```php
<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Reporting\Services\FinancialDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    private const VALID_PERIODS = ['daily', 'weekly', 'monthly'];

    private array $periodToGran = [
        'daily'   => 'day',
        'weekly'  => 'week',
        'monthly' => 'month',
    ];

    public function __invoke(Request $request, FinancialDashboardService $service): Response
    {
        $period = in_array($request->query('period'), self::VALID_PERIODS, true)
            ? $request->query('period')
            : 'monthly';

        $gran = $this->periodToGran[$period];

        return Inertia::render('Dashboard', [
            'revenueSeries'  => $service->revenueByPeriod($gran),
            'expenseSeries'  => $service->expensesByPeriod($gran),
            'arOutstanding'  => $service->arOutstanding(),
            'apOutstanding'  => $service->apOutstanding(),
            'freightByStatus' => $service->freightByStatus(),
            'recentFreights' => $service->recentFreights(),
            'period'         => $period,
        ]);
    }
}
```

### Step 2.4 — Update route

- [ ] **Step 5: Replace dashboard closure in routes/web.php**

In `routes/web.php`, replace:
```php
use Illuminate\Foundation\Application;
```
with:
```php
use App\Modules\Reporting\Http\Controllers\DashboardController;
use Illuminate\Foundation\Application;
```

Then replace:
```php
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', 'tenant'])->name('dashboard');
```
with:
```php
Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified', 'tenant'])->name('dashboard');
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Reporting/DashboardControllerTest.php
```

Expected: All tests pass.

- [ ] **Step 7: Run full suite to check no regressions**

```bash
php artisan test
```

Expected: 242+ tests pass, 0 failures.

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Reporting/ tests/Feature/Reporting/DashboardControllerTest.php routes/web.php
git commit -m "feat(reporting): add FinancialDashboardService and DashboardController"
```

---

## Task 3: Dashboard.vue — full financial dashboard

**Files:**
- Modify: `resources/js/Pages/Dashboard.vue`

- [ ] **Step 1: Replace Dashboard.vue with full financial dashboard**

Replace the entire contents of `resources/js/Pages/Dashboard.vue`:

```vue
<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import VueApexCharts from 'vue3-apexcharts'

const STATUS_LABELS = {
    to_start:        'A Iniciar',
    in_route:        'Em Rota',
    finished:        'Finalizado',
    awaiting_payment: 'Aguard. Pagamento',
    completed:       'Concluído',
}

const STATUS_COLORS_APEX = {
    to_start:         '#94a3b8',
    in_route:         '#3b82f6',
    finished:         '#f59e0b',
    awaiting_payment: '#f97316',
    completed:        '#22c55e',
}

const PERIOD_LABELS = { monthly: 'Mensal', weekly: 'Semanal', daily: 'Diário' }

export default {
    components: { AuthenticatedLayout, Head, apexchart: VueApexCharts },

    props: {
        revenueSeries:   { type: Array, default: () => [] },
        expenseSeries:   { type: Array, default: () => [] },
        arOutstanding:   { type: [String, Number], default: '0' },
        apOutstanding:   { type: [String, Number], default: '0' },
        freightByStatus: { type: Object, default: () => ({}) },
        recentFreights:  { type: Array, default: () => [] },
        period:          { type: String, default: 'monthly' },
    },

    computed: {
        periodLabel() { return PERIOD_LABELS[this.period] ?? 'Mensal' },

        totalRevenue() {
            return this.revenueSeries.reduce((s, p) => s + p.y, 0)
        },

        totalExpenses() {
            return this.expenseSeries.reduce((s, p) => s + p.y, 0)
        },

        areaChartOptions() {
            return {
                chart: {
                    type: 'area',
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    fontFamily: 'inherit',
                },
                stroke:    { curve: 'smooth', width: 2 },
                fill:      { type: 'gradient', gradient: { opacityFrom: 0.25, opacityTo: 0.02 } },
                colors:    ['#4f46e5', '#f59e0b'],
                xaxis:     { type: 'datetime', labels: { datetimeUTC: false } },
                yaxis:     { labels: { formatter: v => 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 0 }) } },
                tooltip:   { x: { format: 'dd MMM yyyy' }, y: { formatter: v => this.formatCurrency(v) } },
                legend:    { position: 'top', horizontalAlign: 'right' },
                grid:      { borderColor: '#f1f5f9' },
                dataLabels: { enabled: false },
            }
        },

        areaChartSeries() {
            return [
                { name: 'Receita', data: this.revenueSeries },
                { name: 'Despesas', data: this.expenseSeries },
            ]
        },

        statusChartOptions() {
            const labels = Object.keys(this.freightByStatus).map(s => STATUS_LABELS[s] ?? s)
            const colors = Object.keys(this.freightByStatus).map(s => STATUS_COLORS_APEX[s] ?? '#94a3b8')
            return {
                chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
                plotOptions: { bar: { horizontal: true, barHeight: '60%', borderRadius: 4 } },
                xaxis: { categories: labels, labels: { show: true } },
                colors,
                dataLabels: { enabled: false },
                grid: { borderColor: '#f1f5f9' },
                tooltip: { y: { formatter: v => `${v} fretes` } },
            }
        },

        statusChartSeries() {
            return [{ name: 'Fretes', data: Object.values(this.freightByStatus) }]
        },
    },

    methods: {
        setPeriod(p) {
            router.get('/dashboard', { period: p }, { preserveState: false })
        },

        formatCurrency(val) {
            if (val === null || val === undefined) return '—'
            return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },

        statusLabel(s) { return STATUS_LABELS[s] ?? s },

        statusColor(s) {
            const map = {
                to_start:         'bg-gray-100 text-gray-700',
                in_route:         'bg-blue-100 text-blue-700',
                finished:         'bg-yellow-100 text-yellow-700',
                awaiting_payment: 'bg-orange-100 text-orange-700',
                completed:        'bg-green-100 text-green-700',
            }
            return map[s] ?? 'bg-gray-100 text-gray-600'
        },
    },
}
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
                <!-- Period tabs -->
                <div class="flex gap-1 rounded-lg bg-gray-100 p-1">
                    <button v-for="(label, key) in { monthly: 'Mensal', weekly: 'Semanal', daily: 'Diário' }"
                        :key="key"
                        @click="setPeriod(key)"
                        :class="[
                            'rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                            period === key
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700',
                        ]">
                        {{ label }}
                    </button>
                </div>
            </div>
        </template>

        <!-- KPI row -->
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-xl bg-white px-6 py-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Receita ({{ periodLabel }})</p>
                <p class="mt-2 text-2xl font-bold text-indigo-600">{{ formatCurrency(totalRevenue) }}</p>
            </div>
            <div class="rounded-xl bg-white px-6 py-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Despesas ({{ periodLabel }})</p>
                <p class="mt-2 text-2xl font-bold text-amber-600">{{ formatCurrency(totalExpenses) }}</p>
            </div>
            <div class="rounded-xl bg-white px-6 py-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">A Receber</p>
                <p class="mt-2 text-2xl font-bold text-emerald-600">{{ formatCurrency(arOutstanding) }}</p>
            </div>
            <div class="rounded-xl bg-white px-6 py-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">A Pagar</p>
                <p class="mt-2 text-2xl font-bold text-red-600">{{ formatCurrency(apOutstanding) }}</p>
            </div>
        </div>

        <!-- Charts row -->
        <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Revenue vs Expenses area chart -->
            <div class="col-span-2 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <h2 class="mb-4 text-sm font-semibold text-gray-700">Receita vs. Despesas</h2>
                <apexchart
                    v-if="revenueSeries.length || expenseSeries.length"
                    type="area"
                    height="220"
                    :options="areaChartOptions"
                    :series="areaChartSeries"
                />
                <p v-else class="py-16 text-center text-sm text-gray-400">Nenhum dado para o período.</p>
            </div>

            <!-- Freight by status bar chart -->
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <h2 class="mb-4 text-sm font-semibold text-gray-700">Fretes por Status</h2>
                <apexchart
                    v-if="Object.keys(freightByStatus).length"
                    type="bar"
                    height="220"
                    :options="statusChartOptions"
                    :series="statusChartSeries"
                />
                <p v-else class="py-16 text-center text-sm text-gray-400">Nenhum frete cadastrado.</p>
            </div>
        </div>

        <!-- Recent freights -->
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-semibold text-gray-700">Fretes Recentes</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">#</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Veículo</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Valor</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-if="recentFreights.length === 0">
                        <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400">Nenhum frete cadastrado.</td>
                    </tr>
                    <tr v-for="f in recentFreights" :key="f.id"
                        class="cursor-pointer hover:bg-gray-50 transition-colors"
                        @click="$inertia.visit(`/freights/${f.id}`)">
                        <td class="px-6 py-3.5 text-sm font-medium text-gray-900">#{{ f.id }}</td>
                        <td class="px-6 py-3.5 text-sm text-gray-700">{{ f.client?.name ?? '—' }}</td>
                        <td class="px-6 py-3.5 font-mono text-sm text-gray-700">{{ f.vehicle?.license_plate ?? '—' }}</td>
                        <td class="px-6 py-3.5 text-right text-sm text-gray-900">{{ formatCurrency(f.freight_value) }}</td>
                        <td class="px-6 py-3.5">
                            <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusColor(f.status)]">
                                {{ statusLabel(f.status) }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 2: Build assets**

```bash
npm run build 2>&1 | tail -10
```

Expected: Build completes with no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Dashboard.vue
git commit -m "feat(frontend): implement full financial dashboard with ApexCharts"
```

---

## Task 4: VehicleReportService + VehicleReportController + tests

**Files:**
- Create: `tests/Feature/Reporting/VehicleReportControllerTest.php`
- Create: `app/Modules/Reporting/Services/VehicleReportService.php`
- Create: `app/Modules/Reporting/Http/Controllers/VehicleReportController.php`
- Modify: `routes/web.php`

### Step 4.1 — Write failing tests

- [ ] **Step 1: Create test file**

Create `tests/Feature/Reporting/VehicleReportControllerTest.php`:

```php
<?php

namespace Tests\Feature\Reporting;

use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\FuelRecord;
use App\Modules\Finance\Models\MaintenanceRecord;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class VehicleReportControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_financial_can_access_vehicle_report_index(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user)->get('/reports/vehicles')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Reporting/Vehicles'));
    }

    public function test_admin_can_access_vehicle_report_index(): void
    {
        $user = $this->makeUserWithRole('Admin');
        $this->actingAsTenant($user)->get('/reports/vehicles')->assertOk();
    }

    public function test_operator_cannot_access_vehicle_report(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $this->actingAsTenant($user)->get('/reports/vehicles')->assertForbidden();
    }

    public function test_vehicle_report_index_only_includes_own_company_vehicles(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        Vehicle::factory()->create(['company_id' => $userA->company_id, 'kind' => 'vehicle']);
        Vehicle::factory()->create(['company_id' => $userA->company_id, 'kind' => 'vehicle']);
        Vehicle::factory()->create(['company_id' => $userB->company_id, 'kind' => 'vehicle']);

        $this->actingAsTenant($userA)->get('/reports/vehicles')
            ->assertInertia(fn ($p) => $p->has('vehicles', 2));
    }

    public function test_vehicle_show_returns_correct_component_and_metrics(): void
    {
        $user    = $this->makeUserWithRole('Financial');
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id, 'kind' => 'vehicle']);

        Freight::factory()->create([
            'company_id'    => $user->company_id,
            'vehicle_id'    => $vehicle->id,
            'freight_value' => '2000.00',
            'finished_at'   => now(),
        ]);
        FuelRecord::factory()->create([
            'company_id' => $user->company_id,
            'vehicle_id' => $vehicle->id,
            'total_cost' => '300.00',
        ]);
        MaintenanceRecord::factory()->create([
            'company_id' => $user->company_id,
            'vehicle_id' => $vehicle->id,
            'cost'       => '500.00',
        ]);

        $this->actingAsTenant($user)->get("/reports/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Reporting/VehicleShow')
                ->where('metrics.revenue', '2000.00')
                ->where('metrics.fuel_cost', '300.00')
                ->where('metrics.maintenance_cost', '500.00')
                ->where('metrics.freight_count', 1)
            );
    }

    public function test_vehicle_show_is_scoped_to_own_company(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        $vehicleB = Vehicle::factory()->create(['company_id' => $userB->company_id, 'kind' => 'vehicle']);

        $this->actingAsTenant($userA)->get("/reports/vehicles/{$vehicleB->id}")
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Reporting/VehicleReportControllerTest.php
```

Expected: Route not found or class not found errors.

### Step 4.2 — Implement VehicleReportService

- [ ] **Step 3: Create the service**

Create `app/Modules/Reporting/Services/VehicleReportService.php`:

```php
<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\Models\Freight;

class VehicleReportService
{
    public function allVehiclesWithMetrics(): \Illuminate\Support\Collection
    {
        return Vehicle::query()
            ->where('kind', 'vehicle')
            ->select('vehicles.*')
            ->selectRaw('
                (SELECT COALESCE(SUM(freight_value), 0)
                 FROM freights
                 WHERE vehicle_id = vehicles.id
                   AND freight_value IS NOT NULL
                   AND company_id = vehicles.company_id) AS revenue
            ')
            ->selectRaw('
                (SELECT COALESCE(SUM(total_cost), 0)
                 FROM fuel_records
                 WHERE vehicle_id = vehicles.id
                   AND company_id = vehicles.company_id) AS fuel_cost
            ')
            ->selectRaw('
                (SELECT COALESCE(SUM(cost), 0)
                 FROM maintenance_records
                 WHERE vehicle_id = vehicles.id
                   AND company_id = vehicles.company_id) AS maintenance_cost
            ')
            ->selectRaw('
                (SELECT COUNT(*)
                 FROM freights
                 WHERE vehicle_id = vehicles.id
                   AND company_id = vehicles.company_id) AS freight_count
            ')
            ->selectRaw('
                (SELECT COALESCE(SUM(distance_km), 0)
                 FROM freights
                 WHERE vehicle_id = vehicles.id
                   AND distance_km IS NOT NULL
                   AND company_id = vehicles.company_id) AS total_km
            ')
            ->with('vehicleType')
            ->orderBy('license_plate')
            ->get();
    }

    public function vehicleMetrics(Vehicle $vehicle): array
    {
        return [
            'revenue' => Freight::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereNotNull('freight_value')
                ->sum('freight_value'),

            'fuel_cost' => \App\Modules\Finance\Models\FuelRecord::query()
                ->where('vehicle_id', $vehicle->id)
                ->sum('total_cost'),

            'maintenance_cost' => \App\Modules\Finance\Models\MaintenanceRecord::query()
                ->where('vehicle_id', $vehicle->id)
                ->sum('cost'),

            'freight_count' => Freight::query()
                ->where('vehicle_id', $vehicle->id)
                ->count(),

            'total_km' => Freight::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereNotNull('distance_km')
                ->sum('distance_km'),
        ];
    }

    public function recentFreights(Vehicle $vehicle, int $limit = 10): \Illuminate\Support\Collection
    {
        return Freight::query()
            ->where('vehicle_id', $vehicle->id)
            ->with('client:id,name')
            ->latest()
            ->limit($limit)
            ->get(['id', 'client_id', 'status', 'freight_value', 'distance_km', 'created_at']);
    }
}
```

### Step 4.3 — Implement VehicleReportController

- [ ] **Step 4: Create the controller**

Create `app/Modules/Reporting/Http/Controllers/VehicleReportController.php`:

```php
<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Reporting\Services\VehicleReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VehicleReportController
{
    public function index(Request $request, VehicleReportService $service): Response
    {
        $this->authorize($request);

        return Inertia::render('Reporting/Vehicles', [
            'vehicles' => $service->allVehiclesWithMetrics(),
        ]);
    }

    public function show(Request $request, Vehicle $vehicle, VehicleReportService $service): Response
    {
        $this->authorize($request);

        if ($vehicle->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $vehicle->load('vehicleType');

        return Inertia::render('Reporting/VehicleShow', [
            'vehicle'        => $vehicle,
            'metrics'        => $service->vehicleMetrics($vehicle),
            'recentFreights' => $service->recentFreights($vehicle),
        ]);
    }

    private function authorize(Request $request): void
    {
        $user = $request->user();
        if (! $user->hasAnyRole(['Admin', 'Financial'])) {
            abort(403);
        }
    }
}
```

### Step 4.4 — Register routes

- [ ] **Step 5: Add vehicle report routes to routes/web.php**

In `routes/web.php`, add the following imports at the top:

```php
use App\Modules\Reporting\Http\Controllers\VehicleReportController;
```

Inside the authenticated middleware group, add after the bills routes:

```php
    Route::get('reports/vehicles', [VehicleReportController::class, 'index'])->name('reports.vehicles.index');
    Route::get('reports/vehicles/{vehicle}', [VehicleReportController::class, 'show'])->name('reports.vehicles.show');
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Reporting/VehicleReportControllerTest.php
```

Expected: All tests pass.

- [ ] **Step 7: Run full suite**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Reporting/ tests/Feature/Reporting/VehicleReportControllerTest.php routes/web.php
git commit -m "feat(reporting): add VehicleReportService and VehicleReportController"
```

---

## Task 5: Reporting/Vehicles.vue + Reporting/VehicleShow.vue

**Files:**
- Create: `resources/js/Pages/Reporting/Vehicles.vue`
- Create: `resources/js/Pages/Reporting/VehicleShow.vue`

- [ ] **Step 1: Create Reporting/Vehicles.vue**

Create `resources/js/Pages/Reporting/Vehicles.vue`:

```vue
<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        vehicles: { type: Array, default: () => [] },
    },

    methods: {
        formatCurrency(val) {
            if (val === null || val === undefined || val === '0.00') return 'R$ 0'
            return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },
        formatKm(val) {
            if (!val || Number(val) === 0) return '—'
            return Number(val).toLocaleString('pt-BR') + ' km'
        },
    },
}
</script>

<template>
    <Head title="Relatório de Frota" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Relatório de Frota</h1>
        </template>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Veículo</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Fretes</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Km Total</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Receita</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Combustível</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Manutenção</th>
                        <th class="px-6 py-3.5" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-if="vehicles.length === 0">
                        <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-400">Nenhum veículo cadastrado.</td>
                    </tr>
                    <tr v-for="v in vehicles" :key="v.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <p class="font-mono text-sm font-semibold text-gray-900">{{ v.license_plate }}</p>
                            <p class="text-xs text-gray-500">{{ v.brand }} {{ v.model }} {{ v.year }}</p>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ v.vehicle_type?.label ?? '—' }}</td>
                        <td class="px-6 py-4 text-right text-sm font-medium text-gray-900">{{ v.freight_count }}</td>
                        <td class="px-6 py-4 text-right text-sm text-gray-700">{{ formatKm(v.total_km) }}</td>
                        <td class="px-6 py-4 text-right text-sm font-semibold text-indigo-700">{{ formatCurrency(v.revenue) }}</td>
                        <td class="px-6 py-4 text-right text-sm text-amber-700">{{ formatCurrency(v.fuel_cost) }}</td>
                        <td class="px-6 py-4 text-right text-sm text-red-700">{{ formatCurrency(v.maintenance_cost) }}</td>
                        <td class="px-6 py-4 text-right">
                            <Link :href="`/reports/vehicles/${v.id}`"
                                class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200 transition-colors">
                                Ver
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 2: Create Reporting/VehicleShow.vue**

Create `resources/js/Pages/Reporting/VehicleShow.vue`:

```vue
<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

const STATUS_LABELS = {
    to_start:         'A Iniciar',
    in_route:         'Em Rota',
    finished:         'Finalizado',
    awaiting_payment: 'Aguard. Pagamento',
    completed:        'Concluído',
}

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        vehicle:        { type: Object, required: true },
        metrics:        { type: Object, required: true },
        recentFreights: { type: Array, default: () => [] },
    },

    methods: {
        formatCurrency(val) {
            if (val === null || val === undefined) return '—'
            return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },
        formatKm(val) {
            if (!val || Number(val) === 0) return '—'
            return Number(val).toLocaleString('pt-BR') + ' km'
        },
        statusLabel(s) { return STATUS_LABELS[s] ?? s },
        statusColor(s) {
            const map = {
                to_start:         'bg-gray-100 text-gray-700',
                in_route:         'bg-blue-100 text-blue-700',
                finished:         'bg-yellow-100 text-yellow-700',
                awaiting_payment: 'bg-orange-100 text-orange-700',
                completed:        'bg-green-100 text-green-700',
            }
            return map[s] ?? 'bg-gray-100 text-gray-600'
        },
    },
}
</script>

<template>
    <Head :title="`Frota — ${vehicle.license_plate}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/reports/vehicles" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 font-mono">{{ vehicle.license_plate }}</h1>
                    <p class="text-sm text-gray-500">{{ vehicle.brand }} {{ vehicle.model }} {{ vehicle.year }} — {{ vehicle.vehicle_type?.label }}</p>
                </div>
            </div>
        </template>

        <!-- KPI cards -->
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
            <div class="rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Receita Total</p>
                <p class="mt-2 text-xl font-bold text-indigo-600">{{ formatCurrency(metrics.revenue) }}</p>
            </div>
            <div class="rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Combustível</p>
                <p class="mt-2 text-xl font-bold text-amber-600">{{ formatCurrency(metrics.fuel_cost) }}</p>
            </div>
            <div class="rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Manutenção</p>
                <p class="mt-2 text-xl font-bold text-red-600">{{ formatCurrency(metrics.maintenance_cost) }}</p>
            </div>
            <div class="rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Fretes</p>
                <p class="mt-2 text-xl font-bold text-gray-800">{{ metrics.freight_count }}</p>
            </div>
            <div class="rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Km Total</p>
                <p class="mt-2 text-xl font-bold text-gray-800">{{ formatKm(metrics.total_km) }}</p>
            </div>
        </div>

        <!-- Recent freights -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-semibold text-gray-700">Fretes Recentes</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">#</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Valor</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Km</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-if="recentFreights.length === 0">
                        <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400">Nenhum frete para este veículo.</td>
                    </tr>
                    <tr v-for="f in recentFreights" :key="f.id"
                        class="cursor-pointer hover:bg-gray-50"
                        @click="$inertia.visit(`/freights/${f.id}`)">
                        <td class="px-6 py-3.5 text-sm font-medium text-gray-900">#{{ f.id }}</td>
                        <td class="px-6 py-3.5 text-sm text-gray-700">{{ f.client?.name ?? '—' }}</td>
                        <td class="px-6 py-3.5 text-right text-sm text-gray-900">{{ formatCurrency(f.freight_value) }}</td>
                        <td class="px-6 py-3.5 text-right text-sm text-gray-600">{{ formatKm(f.distance_km) }}</td>
                        <td class="px-6 py-3.5">
                            <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusColor(f.status)]">
                                {{ statusLabel(f.status) }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 3: Build assets**

```bash
npm run build 2>&1 | tail -5
```

Expected: Build completes with no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Reporting/
git commit -m "feat(frontend): add vehicle fleet utilization report pages"
```

---

## Task 6: FreightFinancialSummaryService + FreightController update + Show.vue

**Files:**
- Create: `tests/Feature/Reporting/FreightFinancialSummaryTest.php`
- Create: `app/Modules/Reporting/Services/FreightFinancialSummaryService.php`
- Modify: `app/Modules/Operations/Http/Controllers/FreightController.php`
- Modify: `resources/js/Pages/Operations/Show.vue`

### Step 6.1 — Write failing tests

- [ ] **Step 1: Create test file**

Create `tests/Feature/Reporting/FreightFinancialSummaryTest.php`:

```php
<?php

namespace Tests\Feature\Reporting;

use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\FuelRecord;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class FreightFinancialSummaryTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_freight_show_includes_financial_summary_prop(): void
    {
        $user    = $this->makeUserWithRole('Admin');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->get("/freights/{$freight->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p->has('financialSummary'));
    }

    public function test_financial_summary_includes_linked_receivable(): void
    {
        $user    = $this->makeUserWithRole('Admin');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        Receivable::factory()->create([
            'company_id' => $user->company_id,
            'freight_id' => $freight->id,
            'amount_due' => '1500.00',
            'status'     => 'open',
        ]);

        $this->actingAsTenant($user)->get("/freights/{$freight->id}")
            ->assertInertia(fn ($p) => $p
                ->where('financialSummary.receivable.amount_due', '1500.00')
                ->where('financialSummary.receivable.status', 'open')
            );
    }

    public function test_financial_summary_includes_linked_expenses(): void
    {
        $user    = $this->makeUserWithRole('Admin');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        Expense::factory()->create([
            'company_id' => $user->company_id,
            'freight_id' => $freight->id,
            'amount'     => '250.00',
        ]);

        $this->actingAsTenant($user)->get("/freights/{$freight->id}")
            ->assertInertia(fn ($p) => $p->has('financialSummary.expenses', 1));
    }

    public function test_financial_summary_includes_linked_fuel_records(): void
    {
        $user    = $this->makeUserWithRole('Admin');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        FuelRecord::factory()->create([
            'company_id' => $user->company_id,
            'freight_id' => $freight->id,
            'total_cost' => '180.00',
        ]);

        $this->actingAsTenant($user)->get("/freights/{$freight->id}")
            ->assertInertia(fn ($p) => $p->has('financialSummary.fuel_records', 1));
    }

    public function test_financial_summary_receivable_is_null_when_no_receivable(): void
    {
        $user    = $this->makeUserWithRole('Admin');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->get("/freights/{$freight->id}")
            ->assertInertia(fn ($p) => $p
                ->where('financialSummary.receivable', null)
            );
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Reporting/FreightFinancialSummaryTest.php
```

Expected: Tests fail because `financialSummary` prop doesn't exist yet.

### Step 6.2 — Implement FreightFinancialSummaryService

- [ ] **Step 3: Create the service**

Create `app/Modules/Reporting/Services/FreightFinancialSummaryService.php`:

```php
<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\FuelRecord;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\Models\Freight;

class FreightFinancialSummaryService
{
    public function forFreight(Freight $freight): array
    {
        return [
            'receivable'   => Receivable::query()
                ->where('freight_id', $freight->id)
                ->first(['id', 'status', 'amount_due', 'amount_paid', 'due_date']),

            'expenses'     => Expense::query()
                ->where('freight_id', $freight->id)
                ->with('expenseCategory:id,name')
                ->get(['id', 'expense_category_id', 'amount', 'incurred_on', 'description']),

            'fuel_records' => FuelRecord::query()
                ->where('freight_id', $freight->id)
                ->get(['id', 'liters', 'price_per_liter', 'total_cost', 'fueled_at', 'station']),
        ];
    }
}
```

### Step 6.3 — Update FreightController

- [ ] **Step 4: Inject service into show()**

In `app/Modules/Operations/Http/Controllers/FreightController.php`, add the import at the top:

```php
use App\Modules\Reporting\Services\FreightFinancialSummaryService;
```

Then update the `show` method signature and body. Replace:

```php
    public function show(Freight $freight): Response
    {
        $this->authorize('view', $freight);

        $freight->load([
            'client', 'vehicle.vehicleType', 'trailer', 'driver',
            'fixedRate', 'perKmRate',
            'statusHistory.user',
        ]);

        $tollDefault = null;
        if ($freight->pricing_model === 'fixed' && $freight->fixed_rate_id) {
            $tollDefault = FixedFreightRatePrice::where('fixed_freight_rate_id', $freight->fixed_rate_id)
                ->where('vehicle_type_id', $freight->vehicle->vehicle_type_id)
                ->value('tolls');
        }

        return Inertia::render('Operations/Show', [
            'freight'               => $freight,
            'tollDefault'           => $tollDefault,
            'estimatedLiters'       => $freight->estimatedLiters(),
            'canComputeFreightValue' => $this->canComputeFreightValue($freight),
            'canDelete'             => auth()->user()->can('delete', $freight),
            'rateEditLink'          => $this->rateEditLink($freight),
        ]);
    }
```

with:

```php
    public function show(Freight $freight, FreightFinancialSummaryService $summaryService): Response
    {
        $this->authorize('view', $freight);

        $freight->load([
            'client', 'vehicle.vehicleType', 'trailer', 'driver',
            'fixedRate', 'perKmRate',
            'statusHistory.user',
        ]);

        $tollDefault = null;
        if ($freight->pricing_model === 'fixed' && $freight->fixed_rate_id) {
            $tollDefault = FixedFreightRatePrice::where('fixed_freight_rate_id', $freight->fixed_rate_id)
                ->where('vehicle_type_id', $freight->vehicle->vehicle_type_id)
                ->value('tolls');
        }

        return Inertia::render('Operations/Show', [
            'freight'               => $freight,
            'tollDefault'           => $tollDefault,
            'estimatedLiters'       => $freight->estimatedLiters(),
            'canComputeFreightValue' => $this->canComputeFreightValue($freight),
            'canDelete'             => auth()->user()->can('delete', $freight),
            'rateEditLink'          => $this->rateEditLink($freight),
            'financialSummary'      => $summaryService->forFreight($freight),
        ]);
    }
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Reporting/FreightFinancialSummaryTest.php
```

Expected: All 5 tests pass.

### Step 6.4 — Add financial summary section to Operations/Show.vue

- [ ] **Step 6: Update Operations/Show.vue to accept and display financialSummary prop**

In `resources/js/Pages/Operations/Show.vue`, add `financialSummary` to `props`:

```js
    props: {
        freight: Object,
        tollDefault: { type: [Number, String], default: null },
        estimatedLiters: { type: [Number, String], default: null },
        canComputeFreightValue: { type: Boolean, default: true },
        canDelete: { type: Boolean, default: false },
        rateEditLink: { type: String, default: null },
        financialSummary: { type: Object, default: null },
    },
```

Then, in the template, after the `<!-- Status history -->` section and before the finish modal, add:

```html
            <!-- Financial summary (shown when freight has financial data) -->
            <div v-if="financialSummary && (financialSummary.receivable || financialSummary.expenses.length || financialSummary.fuel_records.length)"
                class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Resumo Financeiro</h2>
                </div>

                <!-- Receivable -->
                <div v-if="financialSummary.receivable" class="border-b border-gray-100 px-6 py-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Conta a Receber</p>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-700">
                            {{ formatCurrency(financialSummary.receivable.amount_due) }} vence em
                            {{ formatDate(financialSummary.receivable.due_date) }}
                        </span>
                        <span :class="[
                            'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                            {
                                'bg-blue-100 text-blue-700':   financialSummary.receivable.status === 'open',
                                'bg-yellow-100 text-yellow-700': financialSummary.receivable.status === 'partially_paid',
                                'bg-green-100 text-green-700':  financialSummary.receivable.status === 'paid',
                                'bg-red-100 text-red-700':      financialSummary.receivable.status === 'overdue',
                            }
                        ]">
                            {{ { open: 'Em Aberto', partially_paid: 'Parcial', paid: 'Pago', overdue: 'Vencido' }[financialSummary.receivable.status] }}
                        </span>
                    </div>
                </div>

                <!-- Expenses linked to this freight -->
                <div v-if="financialSummary.expenses.length" class="border-b border-gray-100 px-6 py-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Despesas</p>
                    <div v-for="e in financialSummary.expenses" :key="e.id"
                        class="flex items-center justify-between py-1 text-sm">
                        <span class="text-gray-700">{{ e.description || e.expense_category?.name || '—' }}</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(e.amount) }}</span>
                    </div>
                </div>

                <!-- Fuel records linked to this freight -->
                <div v-if="financialSummary.fuel_records.length" class="px-6 py-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Abastecimentos</p>
                    <div v-for="f in financialSummary.fuel_records" :key="f.id"
                        class="flex items-center justify-between py-1 text-sm">
                        <span class="text-gray-700">{{ f.liters }} L{{ f.station ? ` — ${f.station}` : '' }}</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(f.total_cost) }}</span>
                    </div>
                </div>
            </div>
```

- [ ] **Step 7: Build assets**

```bash
npm run build 2>&1 | tail -5
```

- [ ] **Step 8: Run full test suite**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 9: Commit**

```bash
git add app/Modules/Reporting/Services/FreightFinancialSummaryService.php \
        app/Modules/Operations/Http/Controllers/FreightController.php \
        resources/js/Pages/Operations/Show.vue \
        tests/Feature/Reporting/FreightFinancialSummaryTest.php
git commit -m "feat(reporting): add financial summary to freight show page"
```

---

## Task 7: Nav update + Relatórios link

**Files:**
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`

- [ ] **Step 1: Add Relatórios nav item to AuthenticatedLayout.vue**

In `resources/js/Layouts/AuthenticatedLayout.vue`, find the `navItems` computed property. After the `'Contas a Pagar'` entry, add:

```js
                {
                    label: 'Relatórios',
                    route: 'reports.vehicles.index',
                    match: 'reports.*',
                    icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />`,
                },
```

- [ ] **Step 2: Build assets**

```bash
npm run build 2>&1 | tail -5
```

- [ ] **Step 3: Run full test suite one final time**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Layouts/AuthenticatedLayout.vue
git commit -m "feat(frontend): add Relatórios nav item linking to vehicle fleet report"
```

---

## Self-Review Checklist

**Spec coverage:**

| Deliverable | Task |
|---|---|
| Financial dashboard (ApexCharts): revenue vs expenses, AR, AP, freight volume, recent activity | Tasks 2, 3 |
| Per-vehicle report: revenue, fuel cost, maintenance cost, utilization | Tasks 4, 5 |
| Per-freight drill-down (financial summary on existing show page) | Task 6 |
| Daily/weekly/monthly aggregation endpoints, period filter | Tasks 2, 3 |
| Read-model service class layer | `FinancialDashboardService`, `VehicleReportService`, `FreightFinancialSummaryService` |

All deliverables covered.

**Placeholder scan:** No TBD or "similar to above" references found. All steps contain actual code.

**Type consistency:** `financialSummary` used in controller output → prop name → template references all consistently `financialSummary`. `revenueSeries`/`expenseSeries` props match controller keys. `metrics` object keys (`revenue`, `fuel_cost`, `maintenance_cost`, `freight_count`, `total_km`) consistent across service, controller, and Vue template.
