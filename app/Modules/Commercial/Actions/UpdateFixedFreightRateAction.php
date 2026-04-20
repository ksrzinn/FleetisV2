<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\FixedFreightRate;

class UpdateFixedFreightRateAction
{
    public function handle(FixedFreightRate $rate, array $data): FixedFreightRate
    {
        $prices = $data['prices'];
        unset($data['prices']);

        $rate->update($data);
        $rate->prices()->delete();

        foreach ($prices as $priceData) {
            $rate->prices()->create(array_merge($priceData, ['company_id' => $rate->company_id]));
        }

        return $rate;
    }
}
