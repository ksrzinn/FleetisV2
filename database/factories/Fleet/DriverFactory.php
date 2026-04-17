<?php

namespace Database\Factories\Fleet;

use App\Modules\Fleet\Models\Driver;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name'       => $this->faker->name(),
            'phone'      => $this->faker->phoneNumber(),
            'birth_date' => $this->faker->date('Y-m-d', '-20 years'),
            'cpf'        => $this->faker->numerify('###.###.###-##'),
            'active'     => true,
        ];
    }
}
