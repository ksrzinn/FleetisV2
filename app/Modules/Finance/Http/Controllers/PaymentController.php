<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\RecordPaymentAction;
use App\Modules\Finance\Http\Requests\StorePaymentRequest;
use App\Modules\Finance\Models\Receivable;
use Illuminate\Http\RedirectResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly RecordPaymentAction $action) {}

    public function store(StorePaymentRequest $request, Receivable $receivable): RedirectResponse
    {
        $this->authorize('recordPayment', $receivable);

        $this->action->handle($receivable, $request->validated());

        return redirect()->route('receivables.show', $receivable)
            ->with('success', 'Pagamento registrado com sucesso.');
    }
}
