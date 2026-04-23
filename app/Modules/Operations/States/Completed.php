<?php

namespace App\Modules\Operations\States;

class Completed extends FreightState
{
    protected static string $name = 'completed';

    public function label(): string { return 'Concluído'; }
}
