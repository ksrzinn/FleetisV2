<?php

namespace App\Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Actions\CreateDriverAction;
use App\Modules\Fleet\Actions\UpdateDriverAction;
use App\Modules\Fleet\Http\Requests\DriverRequest;
use App\Modules\Fleet\Models\Driver;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DriverController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Driver::class);

        $drivers = Driver::when(request('active'), fn ($q, $v) => $q->where('active', $v === 'true'))
            ->when(request('search'), fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Fleet/Drivers/Index', [
            'drivers' => $drivers,
            'filters' => request()->only('active', 'search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Driver::class);

        return Inertia::render('Fleet/Drivers/Form');
    }

    public function store(DriverRequest $request, CreateDriverAction $action): RedirectResponse
    {
        $this->authorize('create', Driver::class);
        $action->handle($request->validated());

        return redirect()->route('drivers.index')->with('success', 'Motorista criado com sucesso.');
    }

    public function edit(Driver $driver): Response
    {
        $this->authorize('update', $driver);

        return Inertia::render('Fleet/Drivers/Form', ['driver' => $driver]);
    }

    public function update(DriverRequest $request, Driver $driver, UpdateDriverAction $action): RedirectResponse
    {
        $this->authorize('update', $driver);
        $action->handle($driver, $request->validated());

        return redirect()->route('drivers.index')->with('success', 'Motorista atualizado com sucesso.');
    }

    public function destroy(Driver $driver): RedirectResponse
    {
        $this->authorize('delete', $driver);
        $driver->delete();

        return redirect()->route('drivers.index')->with('success', 'Motorista removido com sucesso.');
    }
}
