<?php

namespace App\Modules\Fleet\Actions;

use App\Modules\Fleet\Models\Driver;

class CreateDriverAction
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): Driver
    {
        return Driver::create($data);
    }
}
