<?php

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

    public function create(): Response
    {
        $this->authorize('create', Bill::class);

        return Inertia::render('Finance/Bills/Form');
    }

    public function store(StoreBillRequest $request, GenerateInstallmentsAction $action): RedirectResponse
    {
        $this->authorize('create', Bill::class);

        $bill = $action->handle($request->validated());

        return redirect()->route('bills.show', $bill)
            ->with('success', 'Conta criada com sucesso.');
    }

    public function show(Bill $bill): Response
    {
        $this->authorize('view', $bill);

        $bill->load(['installments.payments']);

        return Inertia::render('Finance/Bills/Show', [
            'bill'                => $bill,
            'outstanding_balance' => $bill->outstandingBalance(),
            'methods'             => ['pix', 'transferencia', 'dinheiro', 'cheque', 'boleto'],
        ]);
    }

    public function edit(Bill $bill): Response
    {
        $this->authorize('update', $bill);

        return Inertia::render('Finance/Bills/Form', ['bill' => $bill]);
    }

    public function update(UpdateBillRequest $request, Bill $bill, UpdateBillAction $action): RedirectResponse
    {
        $this->authorize('update', $bill);

        $action->handle($bill, $request->validated());

        return redirect()->route('bills.show', $bill)
            ->with('success', 'Conta atualizada com sucesso.');
    }

    public function destroy(Bill $bill): RedirectResponse
    {
        $this->authorize('delete', $bill);

        if ($bill->hasPayments()) {
            abort(403, 'Não é possível excluir uma conta com pagamentos registrados.');
        }

        $bill->delete();

        return redirect()->route('bills.index')
            ->with('success', 'Conta removida com sucesso.');
    }
}
