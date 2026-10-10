<?php

namespace App\States\Kyc;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class KycState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, UnderReview::class)
            ->allowTransition(UnderReview::class, Approved::class)
            ->allowTransition(UnderReview::class, Rejected::class)
            ->allowTransition(Rejected::class, Pending::class); // resubmit
    }
}
