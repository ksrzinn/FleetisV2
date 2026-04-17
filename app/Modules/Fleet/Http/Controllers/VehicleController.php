<?php

namespace App\Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Actions\CreateVehicleAction;
use App\Modules\Fleet\Actions\UpdateVehicleAction;
use App\Modules\Fleet\Http\Requests\VehicleRequest;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Vehicle::class);

        $vehicles = Vehicle::with('vehicleType')
            ->when(request('active'), fn ($q, $v) => $q->where('active', $v === 'true'))
            ->when(request('search'), fn ($q, $s) => $q->where('license_plate', 'ilike', "%{$s}%"))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Fleet/Vehicles/Index', [
            'vehicles'     => $vehicles,
            'vehicleTypes' => VehicleType::orderBy('label')->get(['id', 'code', 'label', 'requires_trailer']),
            'filters'      => request()->only('active', 'search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Vehicle::class);

        return Inertia::render('Fleet/Vehicles/Form', [
            'vehicleTypes' => VehicleType::orderBy('label')->get(['id', 'code', 'label', 'requires_trailer']),
        ]);
    }

    public function store(VehicleRequest $request, CreateVehicleAction $action): RedirectResponse
    {
        $this->authorize('create', Vehicle::class);

        $action->handle($request->validated());

        return redirect()->route('vehicles.index')->with('success', 'Veículo criado com sucesso.');
    }

    public function edit(Vehicle $vehicle): Response
    {
        $this->authorize('update', $vehicle);

        return Inertia::render('Fleet/Vehicles/Form', [
            'vehicle'      => $vehicle->load('vehicleType'),
            'vehicleTypes' => VehicleType::orderBy('label')->get(['id', 'code', 'label', 'requires_trailer']),
        ]);
    }

    public function update(VehicleRequest $request, Vehicle $vehicle, UpdateVehicleAction $action): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        $action->handle($vehicle, $request->validated());

        return redirect()->route('vehicles.index')->with('success', 'Veículo atualizado com sucesso.');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('delete', $vehicle);

        $vehicle->delete();

        return redirect()->route('vehicles.index')->with('success', 'Veículo removido com sucesso.');
    }
}
