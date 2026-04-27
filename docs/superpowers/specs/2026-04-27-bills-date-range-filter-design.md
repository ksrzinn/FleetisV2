# Bills Date Range Filter — Design Spec

**Date:** 2026-04-27
**Scope:** `Finance/Bills/Index.vue` + `BillController::index()`

---

## Summary

Add a date range filter to the Bills index page. Users can either type custom `from`/`to` dates or click preset shortcuts ("Hoje", "Esta Semana", "Este Mês") for fast access. The active preset is visually highlighted.

---

## Filter Bar Layout

```
[Tipo ▼]  [Buscar fornecedor...]  [De: date]  [Até: date]  [Hoje] [Esta Semana] [Este Mês]
```

All filters live in the existing `flex flex-wrap gap-3` filter row — no layout changes needed.

---

## Preset Behavior

| Label       | `dueDateFrom`           | `dueDateTo`             |
|-------------|-------------------------|-------------------------|
| Hoje        | today (YYYY-MM-DD)      | today (YYYY-MM-DD)      |
| Esta Semana | Monday of current week  | Sunday of current week  |
| Este Mês    | 1st of current month    | Last day of current month |

- Clicking an **inactive** preset sets both date fields to the preset's range and fires `applyFilters`.
- Clicking an **active** preset clears both date fields and fires `applyFilters` (toggle off).
- Changing a date input manually fires `applyFilters` and deactivates any preset (since the range no longer matches).

---

## Active State Detection

A preset is considered active when `dueDateFrom` and `dueDateTo` exactly match the values that preset would produce. Computed as a method `activePreset()` returning `'today' | 'week' | 'month' | null`.

**Active style:** `bg-indigo-600 text-white` (filled indigo, consistent with the "+ Nova Conta" button).
**Inactive style:** `border border-gray-300 text-gray-700 hover:bg-gray-50` (matches existing filter inputs' border style).

---

## Frontend Changes (`Finance/Bills/Index.vue`)

**New `data()` fields:**
```js
dueDateFrom: this.filters?.due_date_from ?? '',
dueDateTo:   this.filters?.due_date_to   ?? '',
```

**New `applyFilters()` params:**
```js
due_date_from: this.dueDateFrom || undefined,
due_date_to:   this.dueDateTo   || undefined,
```

**New method `activePreset()`:** returns which preset (if any) matches the current date range.

**New method `applyPreset(preset)`:** sets `dueDateFrom`/`dueDateTo` from preset (or clears if already active) then calls `applyFilters()`.

**New UI — date inputs:**
```html
<input type="date" v-model="dueDateFrom" @change="applyFilters" ... />
<input type="date" v-model="dueDateTo"   @change="applyFilters" ... />
```

**New UI — preset buttons:**
```html
<button @click="applyPreset('today')"  :class="presetClass('today')">Hoje</button>
<button @click="applyPreset('week')"   :class="presetClass('week')">Esta Semana</button>
<button @click="applyPreset('month')"  :class="presetClass('month')">Este Mês</button>
```

`presetClass(preset)` returns active or inactive Tailwind classes based on `activePreset()`.

---

## Backend Changes (`BillController::index()`)

Add two `->when()` clauses:
```php
->when(request('due_date_from'), fn ($q, $d) => $q->where('due_date', '>=', $d))
->when(request('due_date_to'),   fn ($q, $d) => $q->where('due_date', '<=', $d))
```

Expose new params in filters:
```php
'filters' => request()->only('bill_type', 'supplier', 'due_date_from', 'due_date_to'),
```

---

## Testing

- Backend: filter by `due_date_from` only, `due_date_to` only, both together, and neither — assert correct bill set returned.
- Frontend: no JS unit tests needed (thin logic, tested via feature tests).

---

## Out of Scope

- Filtering by `created_at` or payment date.
- Presets beyond daily/weekly/monthly.
- Persisting filter state across sessions.
