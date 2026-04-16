<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\RegisterCompanyAction;
use App\Modules\Identity\Http\Requests\CompanyRegisterRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CompanyRegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/RegisterCompany');
    }

    public function store(CompanyRegisterRequest $request, RegisterCompanyAction $action): RedirectResponse
    {
        $user = $action->handle($request->validated());

        event(new Registered($user));
        Auth::login($user);

        return redirect()->intended('/dashboard');
    }
}
