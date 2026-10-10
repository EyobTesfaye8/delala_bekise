<?php

namespace App\States\ProposalItem;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ProposalItemState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, AcceptedForEscrow::class)
            ->allowTransition(Pending::class, Rejected::class);
    }
}
