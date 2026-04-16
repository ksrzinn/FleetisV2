<?php

namespace Database\Factories\Tenancy;

use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Tenancy\Models\Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'cnpj' => fake()->unique()->numerify('##############'),
            'timezone' => 'America/Sao_Paulo',
            'status' => 'active',
        ];
    }
}
