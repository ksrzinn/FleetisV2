<?php

namespace Database\Factories\Commercial;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\PerKmFreightRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PerKmFreightRate> */
class PerKmFreightRateFactory extends Factory
{
    protected $model = PerKmFreightRate::class;

    private static array $brazilianStates = ['SP', 'RJ', 'MG', 'RS', 'PR', 'SC', 'BA', 'GO'];

    private static int $stateIndex = 0;

    public function definition(): array
    {
        // Must eagerly create Client so company_id and client_id can be correlated.
        $client = Client::factory()->create();

        return [
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'state' => self::$brazilianStates[self::$stateIndex++ % count(self::$brazilianStates)],
            'rate_per_km' => fake()->randomFloat(4, 1, 10),
        ];
    }
}
