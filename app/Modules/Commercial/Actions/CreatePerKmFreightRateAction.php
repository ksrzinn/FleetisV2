<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\PerKmFreightRate;

class CreatePerKmFreightRateAction
{
    public function handle(Client $client, array $data): PerKmFreightRate
    {
        $prices = $data['prices'];
        unset($data['prices']);

        $rate = $client->perKmRates()->create(array_merge($data, ['company_id' => $client->company_id]));

        foreach ($prices as $priceData) {
            $rate->prices()->create(array_merge($priceData, ['company_id' => $client->company_id]));
        }

        return $rate;
    }
}
