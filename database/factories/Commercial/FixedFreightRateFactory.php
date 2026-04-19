<?php

namespace Database\Factories\Commercial;

use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\FixedFreightRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FixedFreightRate> */
class FixedFreightRateFactory extends Factory
{
    protected $model = FixedFreightRate::class;

    public function definition(): array
    {
        // Must eagerly create ClientFreightTable so company_id can be derived from parent.
        $table = ClientFreightTable::factory()->create();

        return [
            'company_id' => $table->company_id,
            'client_freight_table_id' => $table->id,
            'name' => fake()->city().' '.fake()->randomNumber(1),
            'price' => fake()->randomFloat(2, 100, 5000),
            'avg_km' => null,
            'tolls' => null,
            'fuel_cost' => null,
        ];
    }
}
