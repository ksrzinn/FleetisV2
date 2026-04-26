<?php

namespace App\Modules\Operations\States;

class AwaitingPayment extends FreightState
{
    protected static string $name = 'awaiting_payment';

    public function label(): string { return 'Aguardando Pagamento'; }
}
