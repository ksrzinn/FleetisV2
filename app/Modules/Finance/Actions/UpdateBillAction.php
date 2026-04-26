<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Bill;

class UpdateBillAction
{
    /** @param array<string, mixed> $data */
    public function handle(Bill $bill, array $data): Bill
    {
        // Protect financial fields when payments have already been recorded
        if ($bill->hasPayments()) {
            unset(
                $data['total_amount'],
                $data['installment_count'],
                $data['recurrence_cadence'],
                $data['recurrence_day']
            );
        }

        $bill->update($data);

        return $bill;
    }
}
