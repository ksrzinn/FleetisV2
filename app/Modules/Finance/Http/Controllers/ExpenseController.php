<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\StoreExpenseRequest;
use App\Modules\Finance\Http\Requests\UpdateExpenseRequest;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Expense::class);

        // Supports both ?filter[key]=val (QueryBuilder style) and ?key=val
        $rawFilters = array_merge(
            $request->only(['expense_category_id', 'vehicle_id', 'freight_id', 'date_from', 'date_to']),
            (array) $request->input('filter', [])
        );

        $expenses = Expense::with(['expenseCategory', 'vehicle', 'freight'])
            ->when($rawFilters['expense_category_id'] ?? null, fn ($q, $v) => $q->where('expense_category_id', $v))
            ->when($rawFilters['vehicle_id'] ?? null, fn ($q, $v) => $q->where('vehicle_id', $v))
            ->when($rawFilters['freight_id'] ?? null, fn ($q, $v) => $q->where('freight_id', $v))
            ->when($rawFilters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('incurred_on', '>=', $v))
            ->when($rawFilters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('incurred_on', '<=', $v))
            ->orderByDesc('incurred_on')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Finance/Expenses/Index', [
            'expenses'   => $expenses,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'vehicles'   => Vehicle::orderBy('license_plate')->get(),
            'filters'    => $rawFilters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Expense::class);

        return Inertia::render('Finance/Expenses/Form', [
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'vehicles'   => Vehicle::orderBy('license_plate')->get(),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        Expense::create($request->validated());

        return redirect()->route('expenses.index');
    }

    public function edit(Expense $expense): Response
    {
        $this->authorize('update', $expense);

        return Inertia::render('Finance/Expenses/Form', [
            'expense'    => $expense,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'vehicles'   => Vehicle::orderBy('license_plate')->get(),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        $expense->update($request->validated());

        return redirect()->route('expenses.index');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return redirect()->route('expenses.index');
    }
}
