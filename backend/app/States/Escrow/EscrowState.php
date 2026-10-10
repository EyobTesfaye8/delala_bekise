<?php

namespace App\States\Escrow;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class EscrowState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingPayment::class)
            ->allowTransition(PendingPayment::class, Funded::class)
            ->allowTransition(PendingPayment::class, ForceRefunded::class)
            ->allowTransition(PendingPayment::class, RefundFailed::class)

            ->allowTransition(Funded::class, CancellationRequested::class)
            ->allowTransition(Funded::class, HandoverDeclared::class)
            // Admin can manually refund even from funded in weird cases, or secondary buyer race condition
            ->allowTransition(Funded::class, ForceRefunded::class)
            ->allowTransition(Funded::class, RefundFailed::class)

            ->allowTransition(CancellationRequested::class, ForceRefunded::class) // auto-refund or accepted by delala
            ->allowTransition(CancellationRequested::class, Funded::class) // rejected by delala
            ->allowTransition(CancellationRequested::class, RefundFailed::class)

            ->allowTransition(HandoverDeclared::class, Disputed::class)
            ->allowTransition(HandoverDeclared::class, Released::class) // buyer accepted or 120hr timer expired

            ->allowTransition(Disputed::class, ForceReleased::class)
            ->allowTransition(Disputed::class, ForceRefunded::class)
            ->allowTransition(Disputed::class, RefundFailed::class)

            ->allowTransition(RefundFailed::class, ForceRefunded::class); // manual retry success
    }
}
