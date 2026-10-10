<?php

namespace App\States\Payout;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class PayoutState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Paid::class)
            ->allowTransition(Pending::class, Cancelled::class);
    }
}
