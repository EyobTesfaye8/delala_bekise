<?php

namespace App\Models\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Job state machine.
 * SRS §4.1 Job State Machine.
 */
abstract class JobStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Open::class)
            ->allowTransition(Open::class, Canceled::class)
            ->allowTransition(Open::class, Negotiating::class)
            ->allowTransition(Negotiating::class, Canceled::class)
            ->allowTransition(Negotiating::class, Contracted::class)
            ->allowTransition(Contracted::class, Completed::class)
            ->allowTransition(Contracted::class, Open::class)     // escrow refunded → reopen
            ->allowTransition(Contracted::class, Canceled::class); // escrow refunded → close
    }
}
