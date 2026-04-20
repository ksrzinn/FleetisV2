<?php

namespace App\Modules\Fleet\Actions;

use App\Modules\Fleet\Models\Vehicle;

class UpdateVehicleAction
{
    /** @param array<string, mixed> $data */
    public function handle(Vehicle $vehicle, array $data): Vehicle
    {
        $vehicle->update($data);

        return $vehicle->fresh();
    }
}
