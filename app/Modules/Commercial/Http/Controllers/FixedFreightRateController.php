<?php
namespace App\Modules\Commercial\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commercial\Actions\CreateFixedFreightRateAction;
use App\Modules\Commercial\Actions\UpdateFixedFreightRateAction;
use App\Modules\Commercial\Http\Requests\StoreFixedFreightRateRequest;
use App\Modules\Commercial\Http\Requests\UpdateFixedFreightRateRequest;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Fleet\Models\VehicleType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FixedFreightRateController extends Controller
{
    public function create(ClientFreightTable $freightTable): Response
    {
        $this->authorize('create', FixedFreightRate::class);
        return Inertia::render('Commercial/FixedRates/Create', [
            'freightTable' => $freightTable,
            'vehicleTypes' => VehicleType::orderBy('label')->get(['id', 'label']),
        ]);
    }

    public function store(StoreFixedFreightRateRequest $request, ClientFreightTable $freightTable, CreateFixedFreightRateAction $action): RedirectResponse
    {
        $this->authorize('create', FixedFreightRate::class);
        $action->handle($freightTable, $request->validated());
        return redirect()->route('freight-tables.show', $freightTable)->with('success', 'Rate created.');
    }

    public function edit(FixedFreightRate $fixedRate): Response
    {
        $this->authorize('update', $fixedRate);
        return Inertia::render('Commercial/FixedRates/Edit', [
            'rate' => $fixedRate->load(['freightTable', 'prices.vehicleType']),
            'vehicleTypes' => VehicleType::orderBy('label')->get(['id', 'label']),
        ]);
    }

    public function update(UpdateFixedFreightRateRequest $request, FixedFreightRate $fixedRate, UpdateFixedFreightRateAction $action): RedirectResponse
    {
        $this->authorize('update', $fixedRate);
        $action->handle($fixedRate, $request->validated());
        return redirect()->route('freight-tables.show', $fixedRate->client_freight_table_id)->with('success', 'Rate updated.');
    }

    public function destroy(FixedFreightRate $fixedRate): RedirectResponse
    {
        $this->authorize('delete', $fixedRate);
        $fixedRate->delete();
        return redirect()->route('freight-tables.show', $fixedRate->client_freight_table_id)->with('success', 'Deleted.');
    }
}
