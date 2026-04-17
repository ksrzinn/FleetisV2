<?php

namespace App\Modules\Fleet\Actions;

use App\Modules\Fleet\Models\Driver;

class UpdateDriverAction
{
    /** @param array<string, mixed> $data */
    public function handle(Driver $driver, array $data): Driver
    {
        $driver->update($data);

        return $driver->fresh();
    }
}
