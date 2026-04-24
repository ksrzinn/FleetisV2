<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Receivable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceivableController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Receivable::class);

        $receivables = Receivable::with(['client', 'freight'])
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->due_date_from, fn ($q, $date) => $q->whereDate('due_date', '>=', $date))
            ->when($request->due_date_to, fn ($q, $date) => $q->whereDate('due_date', '<=', $date))
            ->orderBy('due_date')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Finance/Receivables/Index', [
            'receivables' => $receivables,
            'filters'     => $request->only(['status', 'client_id', 'due_date_from', 'due_date_to']),
        ]);
    }

    public function show(Receivable $receivable): Response
    {
        $this->authorize('view', $receivable);

        $receivable->load(['client', 'freight', 'payments']);

        return Inertia::render('Finance/Receivables/Show', [
            'receivable' => $receivable,
            'payments'   => $receivable->payments,
        ]);
    }

}
