<?php

namespace Database\Factories\Commercial;

use App\Modules\Commercial\Models\Client;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->company(),
            'document' => '11144477735',   // known-valid CPF
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('119########'),
            'active' => true,
        ];
    }

    public function cnpj(): static
    {
        return $this->state(['document' => '11222333000181']); // known-valid CNPJ
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
