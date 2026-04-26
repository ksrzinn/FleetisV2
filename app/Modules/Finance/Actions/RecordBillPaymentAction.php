<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\BillInstallment;
use App\Modules\Finance\Models\Payment;

class RecordBillPaymentAction
{
    /** @param array<string, mixed> $data */
    public function handle(BillInstallment $installment, array $data): Payment
    {
        Payment::create([
            'company_id'   => $installment->company_id,
            'payable_type' => 'bill_installment',
            'payable_id'   => $installment->id,
            'amount'       => $data['amount'],
            'paid_at'      => $data['paid_at'],
            'method'       => $data['method'],
            'notes'        => $data['notes'] ?? null,
        ]);

        $newPaid = bcadd((string) ($installment->paid_amount ?? 0), (string) $data['amount'], 2);

        // Temporarily set paid_amount so isFullyPaid() uses the new value
        $installment->paid_amount = $newPaid;
        $isPaid = $installment->isFullyPaid();

        $installment->update([
            'paid_amount' => $newPaid,
            'paid_at'     => $isPaid ? now() : null,
        ]);

        return Payment::where('payable_type', 'bill_installment')
            ->where('payable_id', $installment->id)
            ->latest()
            ->first();
    }
}
