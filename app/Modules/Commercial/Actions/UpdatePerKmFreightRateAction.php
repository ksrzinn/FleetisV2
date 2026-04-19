<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\PerKmFreightRate;

class UpdatePerKmFreightRateAction
{
    public function handle(PerKmFreightRate $rate, array $data): PerKmFreightRate
    {
        $rate->update($data);
        return $rate;
    }
}
