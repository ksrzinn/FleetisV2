<?php

namespace App\Providers;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Commercial\Models\PerKmFreightRate;
use App\Modules\Commercial\Policies\ClientFreightTablePolicy;
use App\Modules\Commercial\Policies\ClientPolicy;
use App\Modules\Commercial\Policies\FixedFreightRatePolicy;
use App\Modules\Commercial\Policies\PerKmFreightRatePolicy;
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

        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(ClientFreightTable::class, ClientFreightTablePolicy::class);
        Gate::policy(FixedFreightRate::class, FixedFreightRatePolicy::class);
        Gate::policy(PerKmFreightRate::class, PerKmFreightRatePolicy::class);

        if ($this->app->environment('testing')) {
            $this->loadMigrationsFrom(database_path('migrations/tests'));
        }

        $this->loadMigrationsFrom(database_path('migrations/rls'));
    }
}
