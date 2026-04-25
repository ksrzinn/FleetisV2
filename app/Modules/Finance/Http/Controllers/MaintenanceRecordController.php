<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\StoreMaintenanceRecordRequest;
use App\Modules\Finance\Http\Requests\UpdateMaintenanceRecordRequest;
use App\Modules\Finance\Models\MaintenanceRecord;
use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MaintenanceRecord::class);

        $rawFilters = array_merge(
            $request->only(['vehicle_id', 'type', 'date_from', 'date_to']),
            (array) $request->input('filter', [])
        );

        $maintenanceRecords = MaintenanceRecord::with(['vehicle'])
            ->when($rawFilters['vehicle_id'] ?? null, fn ($q, $v) => $q->where('vehicle_id', $v))
            ->when($rawFilters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($rawFilters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('performed_on', '>=', $v))
            ->when($rawFilters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('performed_on', '<=', $v))
            ->orderByDesc('performed_on')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Finance/Maintenance/Index', [
            'maintenanceRecords' => $maintenanceRecords,
            'vehicles'           => Vehicle::orderBy('license_plate')->get(),
            'filters'            => $rawFilters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', MaintenanceRecord::class);

        return Inertia::render('Finance/Maintenance/Form', [
            'vehicles' => Vehicle::orderBy('license_plate')->get(),
        ]);
    }

    public function store(StoreMaintenanceRecordRequest $request): RedirectResponse
    {
        $this->authorize('create', MaintenanceRecord::class);

        MaintenanceRecord::create($request->validated());

        return redirect()->route('maintenance-records.index');
    }

    public function edit(MaintenanceRecord $maintenanceRecord): Response
    {
        $this->authorize('update', $maintenanceRecord);

        return Inertia::render('Finance/Maintenance/Form', [
            'maintenanceRecord' => $maintenanceRecord,
            'vehicles'          => Vehicle::orderBy('license_plate')->get(),
        ]);
    }

    public function update(UpdateMaintenanceRecordRequest $request, MaintenanceRecord $maintenanceRecord): RedirectResponse
    {
        $this->authorize('update', $maintenanceRecord);

        $maintenanceRecord->update($request->validated());

        return redirect()->route('maintenance-records.index');
    }

    public function destroy(MaintenanceRecord $maintenanceRecord): RedirectResponse
    {
        $this->authorize('delete', $maintenanceRecord);

        $maintenanceRecord->delete();

        return redirect()->route('maintenance-records.index');
    }
}
