<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\FixedFreightRatePrice;
use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Operations\Actions\CreateFreightAction;
use App\Modules\Operations\Actions\TransitionFreightAction;
use App\Modules\Operations\Http\Requests\StoreFreightRequest;
use App\Modules\Operations\Http\Requests\TransitionFreightRequest;
use App\Modules\Operations\Models\Freight;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FreightController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Freight::class);

        $freights = Freight::with(['client', 'vehicle', 'driver'])
            ->when(request('status'), fn ($q, $s) => $q->where('status', $s))
            ->when(request('search'), fn ($q, $s) => $q->whereHas('client', fn ($cq) => $cq->where('name', 'ilike', "%{$s}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Operations/Index', [
            'freights' => $freights,
            'filters'  => request()->only('status', 'search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Freight::class);

        return Inertia::render('Operations/Create', [
            'clients'      => Client::where('active', true)->orderBy('name')->get(['id', 'name', 'document']),
            'vehicles'     => Vehicle::with('vehicleType')->where('active', true)->where('kind', 'vehicle')->orderBy('license_plate')->get(),
            'trailers'     => Vehicle::where('active', true)->where('kind', 'trailer')->orderBy('license_plate')->get(['id', 'license_plate', 'brand', 'model']),
            'drivers'      => Driver::where('active', true)->orderBy('name')->get(['id', 'name']),
            'vehicleTypes' => VehicleType::all(['id', 'requires_trailer']),
            'brStates'     => $this->brStates(),
        ]);
    }

    public function store(StoreFreightRequest $request, CreateFreightAction $action): RedirectResponse
    {
        $this->authorize('create', Freight::class);

        $freight = $action->handle($request->validated());

        return redirect()->route('freights.show', $freight)->with('success', 'Frete criado com sucesso.');
    }

    public function show(Freight $freight): Response
    {
        $this->authorize('view', $freight);

        $freight->load([
            'client', 'vehicle.vehicleType', 'trailer', 'driver',
            'fixedRate', 'perKmRate',
            'statusHistory.user',
        ]);

        $tollDefault = null;
        if ($freight->pricing_model === 'fixed' && $freight->fixed_rate_id) {
            $tollDefault = FixedFreightRatePrice::where('fixed_freight_rate_id', $freight->fixed_rate_id)
                ->where('vehicle_type_id', $freight->vehicle->vehicle_type_id)
                ->value('tolls');
        }

        return Inertia::render('Operations/Show', [
            'freight'          => $freight,
            'tollDefault'      => $tollDefault,
            'estimatedLiters'  => $freight->estimatedLiters(),
        ]);
    }

    public function transition(TransitionFreightRequest $request, Freight $freight, TransitionFreightAction $action): RedirectResponse
    {
        $this->authorize('transition', $freight);

        $action->handle($freight, $request->validated());

        return back()->with('success', 'Status atualizado.');
    }

    /** @return array<string, string> */
    private function brStates(): array
    {
        return [
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
            'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
            'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
            'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
            'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',
        ];
    }
}
