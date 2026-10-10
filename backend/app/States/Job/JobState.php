<?php

namespace App\States\Job;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class JobState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Open::class)
            ->allowTransition(Open::class, Negotiating::class)
            ->allowTransition(Open::class, Canceled::class)
            ->allowTransition(Negotiating::class, Contracted::class)
            ->allowTransition(Negotiating::class, Canceled::class)
            ->allowTransition(Contracted::class, Completed::class)
            ->allowTransition(Contracted::class, Canceled::class); // Escrow force refunded
    }
}
