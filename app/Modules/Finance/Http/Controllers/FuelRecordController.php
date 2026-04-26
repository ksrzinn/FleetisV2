<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\StoreFuelRecordRequest;
use App\Modules\Finance\Http\Requests\UpdateFuelRecordRequest;
use App\Modules\Finance\Models\FuelRecord;
use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FuelRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FuelRecord::class);

        $rawFilters = array_merge(
            $request->only(['vehicle_id', 'driver_id', 'freight_id', 'date_from', 'date_to']),
            (array) $request->input('filter', [])
        );

        $fuelRecords = FuelRecord::with(['vehicle', 'driver', 'freight'])
            ->when($rawFilters['vehicle_id'] ?? null, fn ($q, $v) => $q->where('vehicle_id', $v))
            ->when($rawFilters['driver_id'] ?? null, fn ($q, $v) => $q->where('driver_id', $v))
            ->when($rawFilters['freight_id'] ?? null, fn ($q, $v) => $q->where('freight_id', $v))
            ->when($rawFilters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('fueled_at', '>=', $v))
            ->when($rawFilters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('fueled_at', '<=', $v))
            ->orderByDesc('fueled_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Finance/FuelRecords/Index', [
            'fuelRecords' => $fuelRecords,
            'vehicles'    => Vehicle::orderBy('license_plate')->get(),
            'drivers'     => Driver::orderBy('name')->get(),
            'filters'     => $rawFilters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', FuelRecord::class);

        return Inertia::render('Finance/FuelRecords/Form', [
            'vehicles' => Vehicle::orderBy('license_plate')->get(),
            'drivers'  => Driver::orderBy('name')->get(),
        ]);
    }

    public function store(StoreFuelRecordRequest $request): RedirectResponse
    {
        $this->authorize('create', FuelRecord::class);

        $data               = $request->validated();
        $data['total_cost'] = bcmul((string) $data['liters'], (string) $data['price_per_liter'], 2);

        FuelRecord::create($data);

        return redirect()->route('fuel-records.index');
    }

    public function edit(FuelRecord $fuelRecord): Response
    {
        $this->authorize('update', $fuelRecord);

        return Inertia::render('Finance/FuelRecords/Form', [
            'fuelRecord' => $fuelRecord,
            'vehicles'   => Vehicle::orderBy('license_plate')->get(),
            'drivers'    => Driver::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateFuelRecordRequest $request, FuelRecord $fuelRecord): RedirectResponse
    {
        $this->authorize('update', $fuelRecord);

        $data               = $request->validated();
        $data['total_cost'] = bcmul((string) $data['liters'], (string) $data['price_per_liter'], 2);

        $fuelRecord->update($data);

        return redirect()->route('fuel-records.index');
    }

    public function destroy(FuelRecord $fuelRecord): RedirectResponse
    {
        $this->authorize('delete', $fuelRecord);

        $fuelRecord->delete();

        return redirect()->route('fuel-records.index');
    }
}
