<?php

namespace App\Modules\Operations\Actions;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\Models\Freight;
use InvalidArgumentException;

class CreateFreightAction
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): Freight
    {
        $vehicle = Vehicle::with('vehicleType')->findOrFail($data['vehicle_id']);
        if ($vehicle->vehicleType->requires_trailer && empty($data['trailer_id'])) {
            throw new InvalidArgumentException('Trailer obrigatório para este tipo de veículo.');
        }

        return Freight::create($data);
    }
}
