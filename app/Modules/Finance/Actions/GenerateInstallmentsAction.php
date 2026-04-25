<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Services\BusinessDayCalculator;
use Illuminate\Support\Carbon;

class GenerateInstallmentsAction
{
    public function __construct(private readonly BusinessDayCalculator $calculator) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data): Bill
    {
        $bill = Bill::create($data);

        match ($bill->bill_type) {
            'one_time'    => $this->generateOneTime($bill),
            'installment' => $this->generateInstallments($bill),
            'recurring'   => $this->generateFirstRecurring($bill),
        };

        return $bill->load('installments');
    }

    private function generateOneTime(Bill $bill): void
    {
        $bill->installments()->create([
            'company_id' => $bill->company_id,
            'sequence'   => 1,
            'amount'     => $bill->total_amount,
            'due_date'   => $bill->due_date->toDateString(),
        ]);
    }

    private function generateFirstRecurring(Bill $bill): void
    {
        $due = $this->calculator->adjustToBusinessDay($bill->due_date->copy());

        $bill->installments()->create([
            'company_id' => $bill->company_id,
            'sequence'   => 1,
            'amount'     => $bill->total_amount,
            'due_date'   => $due->toDateString(),
        ]);
    }

    private function generateInstallments(Bill $bill): void
    {
        $n         = (int) $bill->installment_count;
        $base      = bcdiv((string) $bill->total_amount, (string) $n, 2);
        $total     = bcmul($base, (string) $n, 2);
        $remainder = bcsub((string) $bill->total_amount, $total, 2);
        $dueDate   = $bill->due_date->copy();
        $cadence   = (string) $bill->recurrence_cadence;
        $day       = (int) $bill->recurrence_day;

        for ($i = 1; $i <= $n; $i++) {
            $amount   = ($i === $n) ? bcadd($base, $remainder, 2) : $base;
            $adjusted = $this->calculator->adjustToBusinessDay($dueDate->copy());

            $bill->installments()->create([
                'company_id' => $bill->company_id,
                'sequence'   => $i,
                'amount'     => $amount,
                'due_date'   => $adjusted->toDateString(),
            ]);

            $dueDate = $this->calculator->nextDate($dueDate, $cadence, $day);
        }
    }
}
