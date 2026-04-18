<?php

namespace App\Modules\Fleet\Actions;

use App\Modules\Fleet\Models\Vehicle;

class CreateVehicleAction
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): Vehicle
    {
        return Vehicle::create($data);
    }
}
