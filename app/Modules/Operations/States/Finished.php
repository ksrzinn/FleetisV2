<?php

namespace App\Modules\Operations\States;

class Finished extends FreightState
{
    protected static string $name = 'finished';

    public function label(): string { return 'Finalizado'; }
}
