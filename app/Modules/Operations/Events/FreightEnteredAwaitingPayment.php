<?php

namespace App\Modules\Operations\Events;

use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Events\Dispatchable;

class FreightEnteredAwaitingPayment
{
    use Dispatchable;

    public function __construct(public readonly Freight $freight) {}
}
