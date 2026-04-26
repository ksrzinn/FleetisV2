<?php

namespace App\Modules\Operations\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class FreightState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(ToStart::class)
            ->allowTransition(ToStart::class, InRoute::class)
            ->allowTransition(InRoute::class, Finished::class)
            ->allowTransition(Finished::class, AwaitingPayment::class)
            ->allowTransition(AwaitingPayment::class, Completed::class);
    }

    abstract public function label(): string;
}
