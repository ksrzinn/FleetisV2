<?php

namespace App\Modules\Operations\Observers;

use App\Modules\Operations\Models\Freight;
use App\Modules\Operations\Models\FreightStatusHistory;

class FreightObserver
{
    public function created(Freight $freight): void
    {
        FreightStatusHistory::create([
            'freight_id'  => $freight->id,
            'from_status' => null,
            'to_status'   => (string) $freight->status,
            'user_id'     => auth()->id(),
            'occurred_at' => now(),
        ]);
    }

    public function updated(Freight $freight): void
    {
        if (! $freight->wasChanged('status')) {
            return;
        }

        FreightStatusHistory::create([
            'freight_id'  => $freight->id,
            'from_status' => $freight->getOriginal('status'),
            'to_status'   => (string) $freight->status,
            'user_id'     => auth()->id(),
            'occurred_at' => now(),
        ]);
    }
}
