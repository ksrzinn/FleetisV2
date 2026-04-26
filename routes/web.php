<?php

use App\Http\Controllers\ProfileController;
use App\Modules\Commercial\Http\Controllers\ClientController;
use App\Modules\Commercial\Http\Controllers\ClientFreightTableController;
use App\Modules\Commercial\Http\Controllers\FixedFreightRateController;
use App\Modules\Commercial\Http\Controllers\PerKmFreightRateController;
use App\Modules\Fleet\Http\Controllers\DriverCompensationController;
use App\Modules\Fleet\Http\Controllers\DriverController;
use App\Modules\Fleet\Http\Controllers\VehicleController;
use App\Modules\Finance\Http\Controllers\BillController;
use App\Modules\Finance\Http\Controllers\BillPaymentController;
use App\Modules\Finance\Http\Controllers\ExpenseCategoryController;
use App\Modules\Finance\Http\Controllers\ExpenseController;
use App\Modules\Finance\Http\Controllers\FuelRecordController;
use App\Modules\Finance\Http\Controllers\MaintenanceRecordController;
use App\Modules\Finance\Http\Controllers\PaymentController;
use App\Modules\Finance\Http\Controllers\ReceivableController;
use App\Modules\Operations\Http\Controllers\FreightController;
use App\Modules\Operations\Http\Controllers\FreightRatesController;
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

    Route::resource('clients', ClientController::class);
    Route::resource('clients.freight-tables', ClientFreightTableController::class)->shallow();
    Route::resource('freight-tables.fixed-rates', FixedFreightRateController::class)->shallow();
    Route::resource('clients.per-km-rates', PerKmFreightRateController::class)->shallow();

    Route::resource('freights', FreightController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('freights/{freight}/transition', [FreightController::class, 'transition'])->name('freights.transition');
    Route::get('freight-rates', [FreightRatesController::class, 'index'])->name('freight-rates.index');

    Route::resource('receivables', ReceivableController::class)->only(['index', 'show']);
    Route::post('receivables/{receivable}/payments', [PaymentController::class, 'store'])->name('receivables.payments.store');

    Route::resource('bills', BillController::class);
    Route::post('bill-installments/{installment}/payments', [BillPaymentController::class, 'store'])->name('bill-installments.payments.store');

    Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
    Route::resource('expenses', ExpenseController::class)->except('show');
    Route::resource('fuel-records', FuelRecordController::class)->except('show');
    Route::resource('maintenance-records', MaintenanceRecordController::class)->except('show');
});

require __DIR__.'/auth.php';
