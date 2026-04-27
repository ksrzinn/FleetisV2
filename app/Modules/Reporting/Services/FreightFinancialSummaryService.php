<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\FuelRecord;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\Models\Freight;

class FreightFinancialSummaryService
{
    /**
     * Returns the financial summary for a given freight:
     * linked receivable, expenses, and fuel records.
     *
     * @return array{receivable: Receivable|null, expenses: \Illuminate\Database\Eloquent\Collection, fuel_records: \Illuminate\Database\Eloquent\Collection}
     */
    public function forFreight(Freight $freight): array
    {
        return [
            'receivable'   => Receivable::query()
                ->where('freight_id', $freight->id)
                ->first(['id', 'status', 'amount_due', 'amount_paid', 'due_date']),

            'expenses'     => Expense::query()
                ->where('freight_id', $freight->id)
                ->with('expenseCategory:id,name')
                ->get(['id', 'expense_category_id', 'amount', 'incurred_on', 'description']),

            'fuel_records' => FuelRecord::query()
                ->where('freight_id', $freight->id)
                ->get(['id', 'liters', 'price_per_liter', 'total_cost', 'fueled_at', 'station']),
        ];
    }
}
