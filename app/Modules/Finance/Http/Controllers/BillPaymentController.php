<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\RecordBillPaymentAction;
use App\Modules\Finance\Http\Requests\StoreBillPaymentRequest;
use App\Modules\Finance\Models\BillInstallment;
use Illuminate\Http\RedirectResponse;

class BillPaymentController extends Controller
{
    public function store(
        StoreBillPaymentRequest $request,
        BillInstallment $installment,
        RecordBillPaymentAction $action
    ): RedirectResponse {
        $this->authorize('recordPayment', $installment);

        $action->handle($installment, $request->validated());

        return redirect()->route('bills.show', $installment->bill_id)
            ->with('success', 'Pagamento registrado.');
    }
}
