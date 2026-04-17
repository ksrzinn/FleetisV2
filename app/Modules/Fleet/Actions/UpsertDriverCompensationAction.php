<?php

namespace App\Modules\Fleet\Actions;

use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\DriverCompensation;
use Illuminate\Support\Facades\DB;

class UpsertDriverCompensationAction
{
    /** @param array<string, mixed> $data */
    public function handle(Driver $driver, array $data): DriverCompensation
    {
        return DB::transaction(function () use ($driver, $data) {
            DriverCompensation::where('driver_id', $driver->id)
                ->where('type', $data['type'])
                ->whereNull('effective_to')
                ->update(['effective_to' => now()->toDateString()]);

            return DriverCompensation::create([
                'company_id'     => $driver->company_id,
                'driver_id'      => $driver->id,
                'type'           => $data['type'],
                'percentage'     => $data['percentage'] ?? null,
                'fixed_amount'   => $data['fixed_amount'] ?? null,
                'monthly_salary' => $data['monthly_salary'] ?? null,
                'effective_from' => $data['effective_from'],
                'effective_to'   => null,
            ]);
        });
    }
}
