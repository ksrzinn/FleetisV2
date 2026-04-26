<?php

namespace App\Modules\Operations\States;

class ToStart extends FreightState
{
    protected static string $name = 'to_start';

    public function label(): string { return 'A Iniciar'; }
}
