<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\Actions\TransitionFreightAction;

class RecordPaymentAction
{
    public function __construct(private readonly TransitionFreightAction $transitionAction) {}

    /** @param array<string, mixed> $data */
    public function handle(Receivable $receivable, array $data): Payment
    {
        $payment = Payment::create([
            'company_id'   => $receivable->company_id,
            'payable_type' => 'receivable',
            'payable_id'   => $receivable->id,
            'amount'       => $data['amount'],
            'paid_at'      => $data['paid_at'] ?? now(),
            'method'       => $data['method'],
            'notes'        => $data['notes'] ?? null,
        ]);

        $newAmountPaid = bcadd((string) $receivable->amount_paid, (string) $data['amount'], 2);
        $newStatus = $this->computeStatus($receivable->amount_due, $newAmountPaid);

        $receivable->update([
            'amount_paid' => $newAmountPaid,
            'status'      => $newStatus,
        ]);

        if ($newStatus === 'paid' && $receivable->freight_id) {
            $freight = $receivable->freight;
            if ($freight && (string) $freight->status === 'awaiting_payment') {
                $this->transitionAction->toCompleted($freight);
            }
        }

        return $payment;
    }

    private function computeStatus(mixed $amountDue, string $amountPaid): string
    {
        $cmp = bccomp($amountPaid, (string) $amountDue, 2);

        if ($cmp >= 0) {
            return 'paid';
        }

        if (bccomp($amountPaid, '0.00', 2) > 0) {
            return 'partially_paid';
        }

        return 'open';
    }
}
