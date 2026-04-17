<?php

namespace Database\Factories\Fleet;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'company_id'      => Company::factory(),
            'kind'            => 'vehicle',
            'vehicle_type_id' => VehicleType::factory(),
            'license_plate'   => strtoupper($this->faker->bothify('???-####')),
            'renavam'         => $this->faker->numerify('###########'),
            'brand'           => $this->faker->randomElement(['Volvo', 'Scania', 'Mercedes']),
            'model'           => $this->faker->word(),
            'year'            => $this->faker->numberBetween(2000, 2024),
            'notes'           => null,
            'active'          => true,
        ];
    }
}
