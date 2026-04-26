<?php

namespace Database\Factories\Finance;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Finance\Models\MaintenanceRecord;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceRecord> */
class MaintenanceRecordFactory extends Factory
{
    protected $model = MaintenanceRecord::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'company_id'   => $company->id,
            'vehicle_id'   => Vehicle::factory()->create(['company_id' => $company->id])->id,
            'type'         => $this->faker->randomElement(['preventive', 'corrective', 'emergency', 'routine']),
            'description'  => $this->faker->sentence(),
            'cost'         => $this->faker->randomFloat(2, 50, 10000),
            'odometer_km'  => null,
            'performed_on' => now()->toDateString(),
            'provider'     => null,
        ];
    }
}
