<?php

namespace Database\Factories\Operations;

use App\Modules\Commercial\Models\Client;
use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\Models\Freight;
use App\Modules\Operations\States\AwaitingPayment;
use App\Modules\Operations\States\Finished;
use App\Modules\Operations\States\InRoute;
use App\Modules\Operations\States\ToStart;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Freight> */
class FreightFactory extends Factory
{
    protected $model = Freight::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'company_id'     => $company->id,
            'client_id'      => Client::factory()->create(['company_id' => $company->id])->id,
            'vehicle_id'     => Vehicle::factory()->create(['company_id' => $company->id, 'kind' => 'vehicle'])->id,
            'trailer_id'     => null,
            'driver_id'      => Driver::factory()->create(['company_id' => $company->id])->id,
            'pricing_model'  => 'fixed',
            'fixed_rate_id'  => null,
            'per_km_rate_id' => null,
            'origin'         => null,
            'destination'    => null,
            'status'         => ToStart::class,
        ];
    }

    public function inRoute(): static
    {
        return $this->state(['status' => InRoute::class, 'started_at' => now()]);
    }

    public function finished(): static
    {
        return $this->state([
            'status'      => Finished::class,
            'started_at'  => now()->subHours(3),
            'finished_at' => now(),
        ]);
    }

    public function awaitingPayment(): static
    {
        return $this->state([
            'status'      => AwaitingPayment::class,
            'started_at'  => now()->subHours(4),
            'finished_at' => now()->subHour(),
        ]);
    }
}
