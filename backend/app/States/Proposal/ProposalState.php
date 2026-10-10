<?php

namespace App\States\Proposal;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ProposalState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Negotiating::class)
            ->allowTransition(Pending::class, Withdrawn::class)
            ->allowTransition(Negotiating::class, Accepted::class)
            ->allowTransition(Negotiating::class, Rejected::class)
            // Implicit rejections when another proposal/item is accepted or job is contracted
            ->allowTransition(Pending::class, Rejected::class);
    }
}
