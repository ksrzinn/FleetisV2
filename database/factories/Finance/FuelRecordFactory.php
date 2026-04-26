<?php

namespace Database\Factories\Finance;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Finance\Models\FuelRecord;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FuelRecord> */
class FuelRecordFactory extends Factory
{
    protected $model = FuelRecord::class;

    public function definition(): array
    {
        $company = Company::factory()->create();
        $liters = round($this->faker->randomFloat(3, 10, 200), 3);
        $pricePerLiter = round($this->faker->randomFloat(4, 4, 8), 4);

        return [
            'company_id'      => $company->id,
            'vehicle_id'      => Vehicle::factory()->create(['company_id' => $company->id])->id,
            'driver_id'       => null,
            'freight_id'      => null,
            'liters'          => $liters,
            'price_per_liter' => $pricePerLiter,
            'total_cost'      => round($liters * $pricePerLiter, 2),
            'odometer_km'     => null,
            'fueled_at'       => now()->toDateString(),
            'station'         => null,
        ];
    }
}
