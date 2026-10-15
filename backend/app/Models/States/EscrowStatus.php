<?php

namespace App\Models\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Escrow transaction state machine.
 * SRS §4.3 Escrow Transaction State Machine.
 */
abstract class EscrowStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(EscrowPendingPayment::class)
            ->allowTransition(EscrowPendingPayment::class, EscrowFunded::class)
            ->allowTransition(EscrowFunded::class, EscrowCancellationRequested::class)
            ->allowTransition(EscrowFunded::class, EscrowHandoverDeclared::class)
            ->allowTransition(EscrowCancellationRequested::class, EscrowAutoRefunded::class)
            ->allowTransition(EscrowCancellationRequested::class, EscrowDisputed::class)
            ->allowTransition(EscrowHandoverDeclared::class, EscrowReleased::class)
            ->allowTransition(EscrowHandoverDeclared::class, EscrowDisputed::class)
            ->allowTransition(EscrowDisputed::class, EscrowForceReleased::class)
            ->allowTransition(EscrowDisputed::class, EscrowForceRefunded::class)
            ->allowTransition(EscrowAutoRefunded::class, EscrowRefundFailed::class)
            ->allowTransition(EscrowForceRefunded::class, EscrowRefundFailed::class);
    }
}
