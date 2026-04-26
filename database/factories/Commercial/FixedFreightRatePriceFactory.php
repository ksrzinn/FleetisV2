<?php

namespace Database\Factories\Commercial;

use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Commercial\Models\FixedFreightRatePrice;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FixedFreightRatePrice> */
class FixedFreightRatePriceFactory extends Factory
{
    protected $model = FixedFreightRatePrice::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'fixed_freight_rate_id' => FixedFreightRate::factory(),
            'vehicle_type_id' => VehicleType::factory(),
            'price' => $this->faker->randomFloat(2, 200, 5000),
            'tolls' => $this->faker->randomFloat(2, 0, 500),
            'fuel_cost' => $this->faker->randomFloat(2, 0, 800),
        ];
    }
}
