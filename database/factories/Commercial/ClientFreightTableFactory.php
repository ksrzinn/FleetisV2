<?php

namespace Database\Factories\Commercial;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\ClientFreightTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClientFreightTable> */
class ClientFreightTableFactory extends Factory
{
    protected $model = ClientFreightTable::class;

    public function definition(): array
    {
        // Must eagerly create Client so company_id and client_id can be correlated.
        $client = Client::factory()->create();

        return [
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'name' => fake()->words(3, true),
            'pricing_model' => 'fixed',
            'active' => true,
        ];
    }

    public function perKm(): static
    {
        return $this->state(['pricing_model' => 'per_km']);
    }
}
