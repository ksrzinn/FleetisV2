<?php

namespace App\Modules\Operations\Listeners;

use App\Modules\Operations\Events\FreightEnteredAwaitingPayment;

class CreateReceivableForFreight
{
    // Epic 6: create receivable from $event->freight
    public function handle(FreightEnteredAwaitingPayment $event): void {}
}
