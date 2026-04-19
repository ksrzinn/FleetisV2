<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\FixedFreightRate;

class UpdateFixedFreightRateAction
{
    public function handle(FixedFreightRate $rate, array $data): FixedFreightRate
    {
        $rate->update($data);
        return $rate;
    }
}
