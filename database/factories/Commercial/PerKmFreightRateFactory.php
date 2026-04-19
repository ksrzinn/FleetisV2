<?php

namespace Database\Factories\Commercial;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\PerKmFreightRate;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerKmFreightRateFactory extends Factory
{
    protected $model = PerKmFreightRate::class;

    private static array $states = ['SP', 'RJ', 'MG', 'RS', 'PR', 'SC', 'BA', 'GO'];

    private static int $stateIndex = 0;

    public function definition(): array
    {
        $client = Client::factory()->create();

        return [
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'state' => self::$states[self::$stateIndex++ % count(self::$states)],
            'rate_per_km' => fake()->randomFloat(4, 1, 10),
        ];
    }
}
