<?php

namespace Database\Factories\Commercial;

use App\Modules\Commercial\Models\PerKmFreightRate;
use App\Modules\Commercial\Models\PerKmFreightRatePrice;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PerKmFreightRatePrice> */
class PerKmFreightRatePriceFactory extends Factory
{
    protected $model = PerKmFreightRatePrice::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'per_km_freight_rate_id' => PerKmFreightRate::factory(),
            'vehicle_type_id' => VehicleType::factory(),
            'rate_per_km' => $this->faker->randomFloat(4, 1, 20),
        ];
    }
}
