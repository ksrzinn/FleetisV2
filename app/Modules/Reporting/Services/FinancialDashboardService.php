<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Finance\Models\BillInstallment;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\Models\Freight;
use Illuminate\Support\Facades\DB;

class FinancialDashboardService
{
    /**
     * SUM(freight_value) grouped by date granularity for finished freights.
     * Global scope on Freight handles company_id automatically.
     * Granularity is caller-controlled (only 'day'|'week'|'month' possible).
     *
     * @return array<int, array{x: string, y: string}>
     */
    public function revenueByPeriod(string $granularity): array
    {
        $rows = Freight::query()
            ->whereNotNull('freight_value')
            ->whereNotNull('finished_at')
            ->selectRaw("DATE_TRUNC('{$granularity}', finished_at) AS period, SUM(freight_value) AS total")
            ->groupByRaw("DATE_TRUNC('{$granularity}', finished_at)")
            ->orderByRaw("DATE_TRUNC('{$granularity}', finished_at)")
            ->toBase()
            ->get();

        return $rows->map(fn ($row) => [
            'x' => substr($row->period, 0, 10),
            'y' => (string) $row->total,
        ])->all();
    }

    /**
     * UNION ALL of expenses, fuel records, and maintenance records grouped by date granularity.
     * These models use BelongsToCompany but DB::select bypasses the global scope,
     * so we pass company_id explicitly.
     *
     * @return array<int, array{x: string, y: float}>
     */
    public function expensesByPeriod(string $granularity): array
    {
        $companyId = auth()->user()->company_id;

        $sql = "
            SELECT DATE_TRUNC('{$granularity}', period) AS bucket, SUM(amount) AS total
            FROM (
                SELECT amount, incurred_on AS period
                FROM expenses
                WHERE company_id = ?
                UNION ALL
                SELECT total_cost AS amount, fueled_at AS period
                FROM fuel_records
                WHERE company_id = ?
                UNION ALL
                SELECT cost AS amount, performed_on AS period
                FROM maintenance_records
                WHERE company_id = ?
            ) combined
            GROUP BY DATE_TRUNC('{$granularity}', period)
            ORDER BY bucket
        ";

        $rows = DB::select($sql, [$companyId, $companyId, $companyId]);

        return array_map(fn ($row) => [
            'x' => substr($row->bucket, 0, 10),
            'y' => (string) $row->total,
        ], $rows);
    }

    /**
     * Total outstanding accounts receivable for the authenticated company.
     */
    public function arOutstanding(): string
    {
        return (string) Receivable::query()
            ->whereIn('status', ['open', 'partially_paid', 'overdue'])
            ->selectRaw('COALESCE(SUM(amount_due - amount_paid), 0) AS total')
            ->value('total');
    }

    /**
     * Total outstanding accounts payable for the authenticated company.
     */
    public function apOutstanding(): string
    {
        return (string) BillInstallment::query()
            ->whereNull('paid_at')
            ->selectRaw('COALESCE(SUM(amount - COALESCE(paid_amount, 0)), 0) AS total')
            ->value('total');
    }

    /**
     * Freight count grouped by status for the authenticated company.
     * Uses toBase() to skip model casting (status is a Spatie state object).
     *
     * @return array<string, int>
     */
    public function freightByStatus(): array
    {
        return Freight::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row) => [$row->status => (int) $row->total])
            ->all();
    }

    /**
     * Most recent N freights with client and vehicle relationship data.
     *
     * @return array<int, mixed>
     */
    public function recentFreights(int $limit = 8): array
    {
        return Freight::query()
            ->select(['id', 'client_id', 'vehicle_id', 'status', 'freight_value', 'created_at'])
            ->with([
                'client:id,name',
                'vehicle:id,license_plate,brand,model',
            ])
            ->latest()
            ->limit($limit)
            ->get()
            ->all();
    }
}
