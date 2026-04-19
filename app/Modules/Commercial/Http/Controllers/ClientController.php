<?php
namespace App\Modules\Commercial\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commercial\Actions\CreateClientAction;
use App\Modules\Commercial\Actions\UpdateClientAction;
use App\Modules\Commercial\Http\Requests\StoreClientRequest;
use App\Modules\Commercial\Http\Requests\UpdateClientRequest;
use App\Modules\Commercial\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Client::class);

        $clients = Client::query()
            ->when(request('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('document', 'like', "%{$s}%"))
            ->when(request()->has('active'), fn ($q) => $q->where('active', request()->boolean('active')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Commercial/Clients/Index', [
            'clients' => $clients,
            'filters' => request()->only('search', 'active'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Client::class);
        return Inertia::render('Commercial/Clients/Create');
    }

    public function store(StoreClientRequest $request, CreateClientAction $action): RedirectResponse
    {
        $this->authorize('create', Client::class);
        $action->handle($request->validated());
        return redirect()->route('clients.index')->with('success', 'Client created.');
    }

    public function show(Client $client): Response
    {
        $this->authorize('view', $client);
        $client->load(['freightTables', 'perKmRates']);
        return Inertia::render('Commercial/Clients/Show', ['client' => $client]);
    }

    public function edit(Client $client): Response
    {
        $this->authorize('update', $client);
        return Inertia::render('Commercial/Clients/Edit', ['client' => $client]);
    }

    public function update(UpdateClientRequest $request, Client $client, UpdateClientAction $action): RedirectResponse
    {
        $this->authorize('update', $client);
        $action->handle($client, $request->validated());
        return redirect()->route('clients.index')->with('success', 'Client updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);
        $client->delete();
        return redirect()->route('clients.index')->with('success', 'Client deleted.');
    }
}
