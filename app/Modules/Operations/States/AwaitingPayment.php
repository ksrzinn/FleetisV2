<?php

namespace App\Modules\Operations\States;

class AwaitingPayment extends FreightState
{
    public function label(): string { return 'Aguardando Pagamento'; }
}
