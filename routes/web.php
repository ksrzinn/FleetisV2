<?php

use App\Http\Controllers\ProfileController;
use App\Modules\Commercial\Http\Controllers\ClientController;
use App\Modules\Commercial\Http\Controllers\ClientFreightTableController;
use App\Modules\Commercial\Http\Controllers\FixedFreightRateController;
use App\Modules\Commercial\Http\Controllers\PerKmFreightRateController;
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

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('clients', ClientController::class);
    Route::resource('clients.freight-tables', ClientFreightTableController::class)->shallow();
    Route::resource('freight-tables.fixed-rates', FixedFreightRateController::class)->shallow();
    Route::resource('clients.per-km-rates', PerKmFreightRateController::class)->shallow();
});

require __DIR__.'/auth.php';
