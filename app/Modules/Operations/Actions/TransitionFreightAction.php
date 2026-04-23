<?php

namespace App\Modules\Operations\Actions;

use App\Modules\Commercial\Models\FixedFreightRatePrice;
use App\Modules\Commercial\Models\PerKmFreightRatePrice;
use App\Modules\Operations\Events\FreightEnteredAwaitingPayment;
use App\Modules\Operations\Models\Freight;
use App\Modules\Operations\States\AwaitingPayment;
use App\Modules\Operations\States\Finished;
use App\Modules\Operations\States\InRoute;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

class TransitionFreightAction
{
    /** @param array<string, mixed> $data */
    public function handle(Freight $freight, array $data): Freight
    {
        return match ($data['transition']) {
            'to_in_route'         => $this->toInRoute($freight),
            'to_finished'         => $this->toFinished($freight, $data),
            'to_awaiting_payment' => $this->toAwaitingPayment($freight),
            default               => throw new TransitionNotFound(),
        };
    }

    private function toInRoute(Freight $freight): Freight
    {
        $freight->status->transitionTo(InRoute::class);
        $freight->update(['started_at' => now()]);

        return $freight->fresh();
    }

    /** @param array<string, mixed> $data */
    private function toFinished(Freight $freight, array $data): Freight
    {
        $freight->status->transitionTo(Finished::class);
        $freight->update([
            'distance_km'          => $data['distance_km'] ?? null,
            'toll'                 => $data['toll'] ?? null,
            'fuel_price_per_liter' => $data['fuel_price_per_liter'] ?? null,
            'finished_at'          => now(),
        ]);

        return $freight->fresh();
    }

    private function toAwaitingPayment(Freight $freight): Freight
    {
        $freightValue = $this->computeFreightValue($freight);

        $freight->status->transitionTo(AwaitingPayment::class);
        $freight->update(['freight_value' => $freightValue]);

        FreightEnteredAwaitingPayment::dispatch($freight);

        return $freight->fresh();
    }

    private function computeFreightValue(Freight $freight): ?string
    {
        $vehicleTypeId = $freight->vehicle->vehicle_type_id;

        if ($freight->pricing_model === 'fixed') {
            return FixedFreightRatePrice::where('fixed_freight_rate_id', $freight->fixed_rate_id)
                ->where('vehicle_type_id', $vehicleTypeId)
                ->value('price');
        }

        $rate = PerKmFreightRatePrice::where('per_km_freight_rate_id', $freight->per_km_rate_id)
            ->where('vehicle_type_id', $vehicleTypeId)
            ->value('rate_per_km');

        if (! $rate || ! $freight->distance_km) {
            return null;
        }

        return (string) bcmul((string) $freight->distance_km, (string) $rate, 2);
    }
}
