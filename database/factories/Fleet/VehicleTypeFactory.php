<?php

namespace Database\Factories\Fleet;

use App\Modules\Fleet\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VehicleType> */
class VehicleTypeFactory extends Factory
{
    protected $model = VehicleType::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->slug(1),
            'label' => $this->faker->word(),
            'requires_trailer' => false,
        ];
    }
}
