<?php
namespace App\Modules\Commercial\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commercial\Actions\CreatePerKmFreightRateAction;
use App\Modules\Commercial\Actions\UpdatePerKmFreightRateAction;
use App\Modules\Commercial\Http\Requests\StorePerKmFreightRateRequest;
use App\Modules\Commercial\Http\Requests\UpdatePerKmFreightRateRequest;
use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\PerKmFreightRate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PerKmFreightRateController extends Controller
{
    public function create(Client $client): Response
    {
        $this->authorize('create', PerKmFreightRate::class);
        return Inertia::render('Commercial/PerKmRates/Create', ['client' => $client]);
    }

    public function store(StorePerKmFreightRateRequest $request, Client $client, CreatePerKmFreightRateAction $action): RedirectResponse
    {
        $this->authorize('create', PerKmFreightRate::class);
        $action->handle($client, $request->validated());
        return redirect()->route('clients.show', $client)->with('success', 'Rate created.');
    }

    public function edit(PerKmFreightRate $perKmRate): Response
    {
        $this->authorize('update', $perKmRate);
        return Inertia::render('Commercial/PerKmRates/Edit', ['rate' => $perKmRate->load('client')]);
    }

    public function update(UpdatePerKmFreightRateRequest $request, PerKmFreightRate $perKmRate, UpdatePerKmFreightRateAction $action): RedirectResponse
    {
        $this->authorize('update', $perKmRate);
        $action->handle($perKmRate, $request->validated());
        return redirect()->route('clients.show', $perKmRate->client_id)->with('success', 'Rate updated.');
    }

    public function destroy(PerKmFreightRate $perKmRate): RedirectResponse
    {
        $this->authorize('delete', $perKmRate);
        $perKmRate->delete();
        return redirect()->route('clients.show', $perKmRate->client_id)->with('success', 'Deleted.');
    }
}
