<?php

namespace App\Modules\Operations\States;

class InRoute extends FreightState
{
    protected static string $name = 'in_route';

    public function label(): string { return 'Em Rota'; }
}
