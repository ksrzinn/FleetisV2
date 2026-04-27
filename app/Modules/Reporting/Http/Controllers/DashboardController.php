<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Services\FinancialDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, FinancialDashboardService $service): Response
    {
        $period = $request->query('period', 'monthly');

        $granularity = match ($period) {
            'weekly' => 'week',
            'daily'  => 'day',
            default  => 'month',
        };

        // Normalize invalid period values to the canonical 'monthly'
        if (! in_array($period, ['monthly', 'weekly', 'daily'], true)) {
            $period = 'monthly';
        }

        return Inertia::render('Dashboard', [
            'revenueSeries'  => $service->revenueByPeriod($granularity),
            'expenseSeries'  => $service->expensesByPeriod($granularity),
            'arOutstanding'  => $service->arOutstanding(),
            'apOutstanding'  => $service->apOutstanding(),
            'freightByStatus' => $service->freightByStatus(),
            'recentFreights' => $service->recentFreights(),
            'period'         => $period,
        ]);
    }
}
