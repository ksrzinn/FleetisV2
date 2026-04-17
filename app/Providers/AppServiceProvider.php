<?php

namespace App\Providers;

use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\DriverCompensation;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Policies\DriverCompensationPolicy;
use App\Modules\Fleet\Policies\DriverPolicy;
use App\Modules\Fleet\Policies\VehiclePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->loadMigrationsFrom(database_path('migrations/rls'));

        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(Driver::class, DriverPolicy::class);
        Gate::policy(DriverCompensation::class, DriverCompensationPolicy::class);
    }
}
