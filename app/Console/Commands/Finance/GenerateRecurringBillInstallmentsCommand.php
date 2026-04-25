<?php

namespace App\Console\Commands\Finance;

use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Services\BusinessDayCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateRecurringBillInstallmentsCommand extends Command
{
    protected $signature = 'finance:generate-recurring-installments';

    protected $description = 'Generate the next installment for recurring bills when their next due date has arrived';

    public function __construct(private readonly BusinessDayCalculator $calculator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Bypass RLS: this command operates across all tenants
        DB::statement("SET LOCAL app.current_company_id = ''");

        $generated = 0;

        Bill::where('bill_type', 'recurring')->cursor()->each(function (Bill $bill) use (&$generated) {
            $last = $bill->installments()->orderByDesc('due_date')->first();

            if (! $last) {
                return;
            }

            $nextDue = $this->calculator->nextDate(
                Carbon::parse($last->due_date),
                (string) $bill->recurrence_cadence,
                (int) $bill->recurrence_day
            );

            if ($bill->recurrence_end && $nextDue->gt(Carbon::parse($bill->recurrence_end))) {
                return;
            }

            if ($nextDue->isAfter(today())) {
                return;
            }

            $created = $bill->installments()->firstOrCreate(
                ['due_date' => $nextDue->toDateString()],
                [
                    'company_id' => $bill->company_id,
                    'sequence'   => $last->sequence + 1,
                    'amount'     => $bill->total_amount,
                ]
            );

            if ($created->wasRecentlyCreated) {
                $generated++;
            }
        });

        $this->info("Generated {$generated} recurring installment(s).");

        return self::SUCCESS;
    }
}
