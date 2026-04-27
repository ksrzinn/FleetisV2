<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Reporting\Services\VehicleReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VehicleReportController extends Controller
{
    public function index(Request $request, VehicleReportService $service): Response
    {
        if (! $request->user()->hasAnyRole(['Admin', 'Financial'])) {
            abort(403);
        }

        return Inertia::render('Reporting/Vehicles', [
            'vehicles'                    => $service->allVehiclesWithMetrics(),
            'freightsReceivableOutstanding' => $service->freightsReceivableOutstanding(),
        ]);
    }

    public function show(Request $request, int $vehicle, VehicleReportService $service): Response
    {
        if (! $request->user()->hasAnyRole(['Admin', 'Financial'])) {
            abort(403);
        }

        // Resolve without global scope so a cross-company request returns 403, not 404.
        $vehicle = Vehicle::withoutGlobalScopes()->findOrFail($vehicle);

        if ($vehicle->company_id !== $request->user()->company_id) {
            abort(403);
        }

        $vehicle->load('vehicleType');

        return Inertia::render('Reporting/VehicleShow', [
            'vehicle'       => $vehicle,
            'metrics'       => $service->vehicleMetrics($vehicle),
            'recentFreights' => $service->recentFreights($vehicle),
        ]);
    }
}
