<?php

namespace App\Models\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * KYC status state machine.
 * SRS FR-2.1: PENDING_SUBMISSION → UNDER_REVIEW → APPROVED | REJECTED
 */
abstract class KycStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingSubmission::class)
            ->allowTransition(PendingSubmission::class, UnderReview::class)
            ->allowTransition(UnderReview::class, Approved::class)
            ->allowTransition(UnderReview::class, Rejected::class)
            ->allowTransition(Rejected::class, PendingSubmission::class); // Allow resubmission
    }
}
