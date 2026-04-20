<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\FixedFreightRate;

class CreateFixedFreightRateAction
{
    public function handle(ClientFreightTable $table, array $data): FixedFreightRate
    {
        $prices = $data['prices'];
        unset($data['prices']);

        $rate = $table->fixedRates()->create(
            array_merge($data, ['company_id' => $table->company_id])
        );

        foreach ($prices as $priceData) {
            $rate->prices()->create(array_merge($priceData, ['company_id' => $table->company_id]));
        }

        return $rate;
    }
}
