<?php

namespace App\Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Actions\UpsertDriverCompensationAction;
use App\Modules\Fleet\Http\Requests\DriverCompensationRequest;
use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\DriverCompensation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DriverCompensationController extends Controller
{
    public function index(Driver $driver): Response
    {
        $this->authorize('view', $driver);

        return Inertia::render('Fleet/Drivers/Compensations/Index', [
            'driver'  => $driver,
            'active'  => $driver->activeCompensations()->get(),
            'history' => $driver->compensations()->whereNotNull('effective_to')->orderByDesc('effective_from')->get(),
            'canEdit' => auth()->user()->can('create', DriverCompensation::class),
        ]);
    }

    public function store(
        DriverCompensationRequest $request,
        Driver $driver,
        UpsertDriverCompensationAction $action,
    ): RedirectResponse {
        $this->authorize('view', $driver);
        $this->authorize('create', DriverCompensation::class);

        $action->handle($driver, $request->validated());

        return redirect()
            ->route('drivers.compensations.index', $driver)
            ->with('success', 'Remuneração registrada com sucesso.');
    }
}
