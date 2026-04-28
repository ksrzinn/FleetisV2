# Bills Date Range Filter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `due_date_from` / `due_date_to` query filters plus "Hoje", "Esta Semana", "Este Mês" preset shortcut buttons with an active-state indicator to the Bills index page.

**Architecture:** Backend adds two `->when()` clauses to `BillController::index()` filtering `due_date` by range. Frontend adds two native `<input type="date">` fields and three preset buttons that set those fields; the active preset is highlighted in indigo.

**Tech Stack:** Laravel (PHP), Vue 3 Options API, Inertia.js, Tailwind CSS

---

## Files

| Action | Path |
|--------|------|
| Modify | `app/Modules/Finance/Http/Controllers/BillController.php` |
| Modify | `tests/Feature/Finance/BillControllerTest.php` |
| Modify | `resources/js/Pages/Finance/Bills/Index.vue` |

---

### Task 1: Backend — date range filter + tests

**Files:**
- Modify: `tests/Feature/Finance/BillControllerTest.php`
- Modify: `app/Modules/Finance/Http/Controllers/BillController.php`

- [ ] **Step 1: Write the failing tests**

Append these three tests inside `BillControllerTest` (after the existing index tests, before the Create section):

```php
public function test_index_filters_by_due_date_from(): void
{
    $user = $this->makeUserWithRole('Financial');

    Bill::factory()->create(['company_id' => $user->company_id, 'due_date' => '2026-03-01']);
    Bill::factory()->create(['company_id' => $user->company_id, 'due_date' => '2026-04-15']);
    Bill::factory()->create(['company_id' => $user->company_id, 'due_date' => '2026-05-01']);

    $response = $this->actingAsTenant($user)->get('/bills?due_date_from=2026-04-01');

    $response->assertInertia(fn ($page) => $page->has('bills.data', 2));
}

public function test_index_filters_by_due_date_to(): void
{
    $user = $this->makeUserWithRole('Financial');

    Bill::factory()->create(['company_id' => $user->company_id, 'due_date' => '2026-03-01']);
    Bill::factory()->create(['company_id' => $user->company_id, 'due_date' => '2026-04-15']);
    Bill::factory()->create(['company_id' => $user->company_id, 'due_date' => '2026-05-01']);

    $response = $this->actingAsTenant($user)->get('/bills?due_date_to=2026-04-30');

    $response->assertInertia(fn ($page) => $page->has('bills.data', 2));
}

public function test_index_filters_by_due_date_range(): void
{
    $user = $this->makeUserWithRole('Financial');

    Bill::factory()->create(['company_id' => $user->company_id, 'due_date' => '2026-03-01']);
    Bill::factory()->create(['company_id' => $user->company_id, 'due_date' => '2026-04-15']);
    Bill::factory()->create(['company_id' => $user->company_id, 'due_date' => '2026-05-01']);

    $response = $this->actingAsTenant($user)->get('/bills?due_date_from=2026-04-01&due_date_to=2026-04-30');

    $response->assertInertia(fn ($page) => $page->has('bills.data', 1));
}

public function test_index_filters_exposes_date_params_in_filters_prop(): void
{
    $user = $this->makeUserWithRole('Financial');

    $response = $this->actingAsTenant($user)->get('/bills?due_date_from=2026-04-01&due_date_to=2026-04-30');

    $response->assertInertia(fn ($page) => $page
        ->where('filters.due_date_from', '2026-04-01')
        ->where('filters.due_date_to', '2026-04-30')
    );
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
cd /var/www/html/FleetisV2 && php artisan test tests/Feature/Finance/BillControllerTest.php --filter="due_date" --no-coverage
```

Expected: 4 failures — `due_date_from`/`due_date_to` params are ignored, counts wrong.

- [ ] **Step 3: Implement the backend filter**

In `BillController::index()`, add two `->when()` clauses and update `request()->only()`:

```php
public function index(): Response
{
    $this->authorize('viewAny', Bill::class);

    $bills = Bill::withCount([
        'installments',
        'installments as paid_installments_count' => fn ($q) => $q->whereNotNull('paid_at'),
    ])
        ->when(request('bill_type'),     fn ($q, $t) => $q->where('bill_type', $t))
        ->when(request('supplier'),      fn ($q, $s) => $q->where('supplier', 'ilike', "%{$s}%"))
        ->when(request('due_date_from'), fn ($q, $d) => $q->where('due_date', '>=', $d))
        ->when(request('due_date_to'),   fn ($q, $d) => $q->where('due_date', '<=', $d))
        ->orderByDesc('due_date')
        ->paginate(25)
        ->withQueryString();

    return Inertia::render('Finance/Bills/Index', [
        'bills'   => $bills,
        'filters' => request()->only('bill_type', 'supplier', 'due_date_from', 'due_date_to'),
    ]);
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
cd /var/www/html/FleetisV2 && php artisan test tests/Feature/Finance/BillControllerTest.php --no-coverage
```

Expected: All tests pass, including the 4 new ones.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Http/Controllers/BillController.php tests/Feature/Finance/BillControllerTest.php
git commit -m "feat(finance): add due_date range filter to bills index"
```

---

### Task 2: Frontend — date inputs + preset buttons

**Files:**
- Modify: `resources/js/Pages/Finance/Bills/Index.vue`

- [ ] **Step 1: Add new data fields**

In `data()`, add `dueDateFrom` and `dueDateTo` after `supplier`:

```js
data() {
    return {
        billType:     this.filters?.bill_type      ?? '',
        supplier:     this.filters?.supplier       ?? '',
        dueDateFrom:  this.filters?.due_date_from  ?? '',
        dueDateTo:    this.filters?.due_date_to    ?? '',
        deleteTarget: null,
        typeLabels:   BILL_TYPE_LABELS,
        typeColors:   BILL_TYPE_COLORS,
    }
},
```

- [ ] **Step 2: Update `applyFilters` to include date params**

```js
applyFilters() {
    router.get('/bills', {
        bill_type:     this.billType     || undefined,
        supplier:      this.supplier     || undefined,
        due_date_from: this.dueDateFrom  || undefined,
        due_date_to:   this.dueDateTo    || undefined,
    }, { preserveState: true, replace: true })
},
```

- [ ] **Step 3: Add `activePreset` and `applyPreset` methods**

Add these two methods after `applyFilters`:

```js
activePreset() {
    const pad = (n) => String(n).padStart(2, '0')
    const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`

    const now   = new Date()
    const today = fmt(now)

    // Week: Monday–Sunday
    const dayOfWeek  = (now.getDay() + 6) % 7 // Mon=0 … Sun=6
    const monday     = new Date(now); monday.setDate(now.getDate() - dayOfWeek)
    const sunday     = new Date(monday); sunday.setDate(monday.getDate() + 6)
    const weekStart  = fmt(monday)
    const weekEnd    = fmt(sunday)

    // Month: 1st – last day
    const monthStart = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`
    const lastDay    = new Date(now.getFullYear(), now.getMonth() + 1, 0)
    const monthEnd   = fmt(lastDay)

    if (this.dueDateFrom === today     && this.dueDateTo === today)      return 'today'
    if (this.dueDateFrom === weekStart && this.dueDateTo === weekEnd)    return 'week'
    if (this.dueDateFrom === monthStart && this.dueDateTo === monthEnd)  return 'month'
    return null
},
applyPreset(preset) {
    const pad = (n) => String(n).padStart(2, '0')
    const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`

    const now = new Date()

    if (this.activePreset() === preset) {
        this.dueDateFrom = ''
        this.dueDateTo   = ''
        this.applyFilters()
        return
    }

    if (preset === 'today') {
        const today = fmt(now)
        this.dueDateFrom = today
        this.dueDateTo   = today
    } else if (preset === 'week') {
        const dayOfWeek = (now.getDay() + 6) % 7
        const monday    = new Date(now); monday.setDate(now.getDate() - dayOfWeek)
        const sunday    = new Date(monday); sunday.setDate(monday.getDate() + 6)
        this.dueDateFrom = fmt(monday)
        this.dueDateTo   = fmt(sunday)
    } else if (preset === 'month') {
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0)
        this.dueDateFrom = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`
        this.dueDateTo   = fmt(lastDay)
    }

    this.applyFilters()
},
```

- [ ] **Step 4: Add date inputs and preset buttons to the template**

Replace the existing filter `<div>` (the `mb-5 flex flex-wrap gap-3` block) with:

```html
<!-- Filters -->
<div class="mb-5 flex flex-wrap gap-3 items-center">
    <select v-model="billType" @change="applyFilters"
        class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
        <option value="">Todos os tipos</option>
        <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
    </select>
    <input v-model="supplier" type="text" placeholder="Buscar fornecedor..." @input="applyFilters"
        class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
    <input v-model="dueDateFrom" type="date" @change="applyFilters"
        class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
    <input v-model="dueDateTo" type="date" @change="applyFilters"
        class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
    <button @click="applyPreset('today')"
        :class="activePreset() === 'today'
            ? 'rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm'
            : 'rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50'">
        Hoje
    </button>
    <button @click="applyPreset('week')"
        :class="activePreset() === 'week'
            ? 'rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm'
            : 'rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50'">
        Esta Semana
    </button>
    <button @click="applyPreset('month')"
        :class="activePreset() === 'month'
            ? 'rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm'
            : 'rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50'">
        Este Mês
    </button>
</div>
```

- [ ] **Step 5: Run the full test suite to confirm nothing is broken**

```bash
cd /var/www/html/FleetisV2 && php artisan test tests/Feature/Finance/ --no-coverage
```

Expected: All tests pass.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Finance/Bills/Index.vue
git commit -m "feat(frontend): add date range filter and presets to bills index"
```
