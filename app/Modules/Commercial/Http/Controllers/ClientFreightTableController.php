<?php
namespace App\Modules\Commercial\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commercial\Actions\CreateClientFreightTableAction;
use App\Modules\Commercial\Actions\UpdateClientFreightTableAction;
use App\Modules\Commercial\Http\Requests\StoreClientFreightTableRequest;
use App\Modules\Commercial\Http\Requests\UpdateClientFreightTableRequest;
use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\ClientFreightTable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientFreightTableController extends Controller
{
    public function create(Client $client): Response
    {
        $this->authorize('create', ClientFreightTable::class);
        return Inertia::render('Commercial/FreightTables/Create', ['client' => $client]);
    }

    public function store(StoreClientFreightTableRequest $request, Client $client, CreateClientFreightTableAction $action): RedirectResponse
    {
        $this->authorize('create', ClientFreightTable::class);
        $action->handle($client, $request->validated());
        return redirect()->route('clients.show', $client)->with('success', 'Freight table created.');
    }

    public function show(ClientFreightTable $freightTable): Response
    {
        $this->authorize('view', $freightTable);
        $freightTable->load(['client', 'fixedRates']);
        return Inertia::render('Commercial/FreightTables/Show', ['freightTable' => $freightTable]);
    }

    public function edit(ClientFreightTable $freightTable): Response
    {
        $this->authorize('update', $freightTable);
        return Inertia::render('Commercial/FreightTables/Edit', ['freightTable' => $freightTable]);
    }

    public function update(UpdateClientFreightTableRequest $request, ClientFreightTable $freightTable, UpdateClientFreightTableAction $action): RedirectResponse
    {
        $this->authorize('update', $freightTable);
        $action->handle($freightTable, $request->validated());
        return redirect()->route('freight-tables.show', $freightTable)->with('success', 'Updated.');
    }

    public function destroy(ClientFreightTable $freightTable): RedirectResponse
    {
        $this->authorize('delete', $freightTable);
        $freightTable->delete();
        return redirect()->route('clients.show', $freightTable->client_id)->with('success', 'Deleted.');
    }
}
