<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\PerKmFreightRate;

class CreatePerKmFreightRateAction
{
    public function handle(Client $client, array $data): PerKmFreightRate
    {
        return $client->perKmRates()->create($data);
    }
}
