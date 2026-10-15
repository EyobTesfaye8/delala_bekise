<?php

namespace App\Models\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Proposal state machine.
 * SRS §4.2: PENDING → NEGOTIATING → ACCEPTED | REJECTED | WITHDRAWN
 */
abstract class ProposalStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(ProposalPending::class)
            ->allowTransition(ProposalPending::class, ProposalNegotiating::class)
            ->allowTransition(ProposalPending::class, ProposalWithdrawn::class)
            ->allowTransition(ProposalNegotiating::class, ProposalAccepted::class)
            ->allowTransition(ProposalNegotiating::class, ProposalRejected::class);
    }
}
