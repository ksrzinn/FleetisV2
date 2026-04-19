<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\FixedFreightRate;

class CreateFixedFreightRateAction
{
    public function handle(ClientFreightTable $table, array $data): FixedFreightRate
    {
        return $table->fixedRates()->create(
            array_merge($data, ['company_id' => $table->company_id])
        );
    }
}
