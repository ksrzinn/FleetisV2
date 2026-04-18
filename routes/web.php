<?php

use App\Http\Controllers\ProfileController;
use App\Modules\Fleet\Http\Controllers\DriverCompensationController;
use App\Modules\Fleet\Http\Controllers\DriverController;
use App\Modules\Fleet\Http\Controllers\VehicleController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', 'tenant'])->name('dashboard');

Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('vehicles', VehicleController::class)->except('show');
    Route::resource('drivers', DriverController::class)->except('show');
    Route::resource('drivers.compensations', DriverCompensationController::class)
        ->only(['index', 'store'])
        ->shallow();
});

require __DIR__.'/auth.php';
